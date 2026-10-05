<?php

namespace App\Services;

use Illuminate\Support\Carbon;

/**
 * Parses inbound lead-provider emails into structured lead data.
 *
 * Each provider site sends templated notification emails. A "provider profile"
 * describes (a) how to recognise an email as coming from that site, and
 * (b) how to pull the lead fields out of the email body.
 *
 * To add / adjust a site:
 *   1. Add an entry to $this->providers() below.
 *   2. Give it a `match` block (sender domains and/or subject keywords).
 *   3. Give it a `fields` map of lead-field => list of label aliases.
 *      The parser scans the body for "Label: value" pairs (and a few
 *      common table / HTML layouts) and maps them onto the lead.
 *
 * The `source` value MUST match a chip in the frontend leadSources list
 * (PinLocal, CompareMyMove, reallymoving, ...), otherwise the lead won't
 * appear under its provider section in the UI.
 */
class LeadEmailParser
{
    /**
     * Registry of known lead-provider sites.
     *
     * NOTE: sender domains / subject keywords / label aliases below are
     * sensible defaults for common UK removals lead providers. Adjust them
     * to match the real emails once you forward a sample of each.
     */
    private function providers(): array
    {
        return [
            [
                'source'  => 'Website Form',
                'match'   => [
                    'domains'  => ['gmail.com', 'outlook.com', 'yahoo.com', 'hotmail.com', 'icloud.com'],
                    'subjects' => ['lead', 'enquiry', 'inquiry', 'quote', 'move', 'removal', 'relocation', 'shifting', 'house', 'transfer', 'flat'],
                ],
                'fields'  => [
                    'name'          => ['name', 'customer name', 'full name', 'contact name'],
                    'email'         => ['email', 'email address', 'customer email'],
                    'phone'         => ['phone', 'telephone', 'mobile', 'contact number', 'tel'],
                    'from_location' => ['moving from', 'from', 'collection', 'pickup', 'origin', 'current address'],
                    'to_location'   => ['moving to', 'to', 'delivery', 'destination', 'new address'],
                    'move_type'     => ['move type', 'property', 'property size', 'move size', 'bedrooms', 'service'],
                    'move_date'     => ['move date', 'moving date', 'date', 'preferred date'],
                    'est_value'     => ['estimated value', 'value', 'budget', 'quote', 'estimate'],
                ],
            ],
            [
                'source'  => 'PinLocal',
                'match'   => [
                    'domains'  => ['pinlocal.com', 'pinlocal.co.uk'],
                    'subjects' => ['new lead', 'new enquiry', 'removal lead'],
                ],
                'fields'  => [
                    'name'          => ['name', 'customer name', 'full name', 'contact name'],
                    'email'         => ['email', 'email address', 'customer email'],
                    'phone'         => ['phone', 'telephone', 'mobile', 'contact number', 'tel'],
                    'from_location' => ['moving from', 'from', 'collection', 'pickup', 'origin', 'current address'],
                    'to_location'   => ['moving to', 'to', 'delivery', 'destination', 'new address'],
                    'move_type'     => ['move type', 'property', 'property size', 'move size', 'bedrooms', 'service'],
                    'move_date'     => ['move date', 'moving date', 'date', 'preferred date'],
                    'est_value'     => ['estimated value', 'value', 'budget', 'quote', 'estimate'],
                ],
            ],
            [
                'source'  => 'CompareMyMove',
                'match'   => [
                    'domains'  => ['comparemymove.com'],
                    'subjects' => ['new lead', 'you have a new lead', 'move enquiry'],
                ],
                'fields'  => [
                    'name'          => ['name', 'customer name', 'full name'],
                    'email'         => ['email', 'email address'],
                    'phone'         => ['phone', 'telephone', 'mobile', 'contact number'],
                    'from_location' => ['moving from', 'from postcode', 'from', 'move from'],
                    'to_location'   => ['moving to', 'to postcode', 'to', 'move to'],
                    'move_type'     => ['property size', 'property type', 'move size', 'bedrooms'],
                    'move_date'     => ['move date', 'moving date', 'estimated move date'],
                    'est_value'     => ['estimated value', 'value', 'budget'],
                ],
            ],
            [
                'source'  => 'reallymoving',
                'match'   => [
                    'domains'  => ['reallymoving.com'],
                    'subjects' => ['new lead', 'removals lead', 'new enquiry'],
                ],
                'fields'  => [
                    'name'          => ['name', 'customer name', 'full name'],
                    'email'         => ['email', 'email address'],
                    'phone'         => ['phone', 'telephone', 'mobile', 'tel'],
                    'from_location' => ['moving from', 'from', 'origin postcode', 'from postcode'],
                    'to_location'   => ['moving to', 'to', 'destination postcode', 'to postcode'],
                    'move_type'     => ['property', 'property size', 'move type', 'bedrooms'],
                    'move_date'     => ['move date', 'moving date', 'date of move'],
                    'est_value'     => ['estimated value', 'value', 'estimate'],
                ],
            ],
            [
                'source'  => 'Getamover',
                'match'   => [
                    'domains'  => ['getamover.co.uk', 'getamover.com'],
                    'subjects' => ['quote request', 'new lead', 'enquiry'],
                ],
                'fields'  => [],
            ],
            [
                'source'  => 'Konnectyou',
                'match'   => [
                    'domains'  => ['konnectyou.com', 'konnectyou.co.uk'],
                    'subjects' => ['new lead received', 'ready to contact', 'removals lead', 'konnectyou.com', 'konnectyou'],
                ],
                'fields'  => [],
            ],
        ];
    }

    /**
     * Attempt to parse a Graph message array into structured lead data.
     *
     * @param  array  $msg  A Microsoft Graph message (from fetchEmails value[]).
     * @return array|null   Lead field array ready for Lead::create(), or null
     *                      if this email is not from a recognised provider.
     */
    public function parse(array $msg): ?array
    {
        $fromEmail = strtolower($msg['from']['emailAddress']['address'] ?? '');
        $fromName  = $msg['from']['emailAddress']['name'] ?? '';
        $subject   = $msg['subject'] ?? '';
        $bodyHtml  = $msg['body']['content'] ?? ($msg['bodyPreview'] ?? '');

        $provider = $this->matchProvider($fromEmail, $subject, $bodyHtml);
        if (!$provider) {
            return null;
        }

        $text = $this->htmlToText($bodyHtml);
        $pairs = $this->extractPairs($text);

        $lead = [];
        
        if ($provider['source'] === 'Getamover') {
            $lead = $this->parseGetamover($text);
        } elseif ($provider['source'] === 'Konnectyou') {
            $lead = $this->parseKonnectyou($text);
        } else {
            foreach ($provider['fields'] as $leadField => $aliases) {
                $value = $this->pickValue($pairs, $aliases);
                if ($value !== null && $value !== '') {
                    $lead[$leadField] = $value;
                }
            }
        }

        // Sensible fallbacks so a lead is always usable.
        $lead['source'] = $provider['source'];
        $lead['name']   = ltrim($lead['name']  ?? ($fromName ?: 'Unknown Lead'), ' :-');
        $lead['email']  = ltrim(!empty($lead['email']) ? $lead['email'] : $fromEmail, ' :-');
        $lead['phone']  = ltrim($lead['phone'] ?? '', ' :-');
        $lead['from_location'] = $lead['from_location'] ?? '';
        $lead['to_location']   = $lead['to_location'] ?? '';
        $lead['move_type']     = $lead['move_type'] ?? 'TBC';

        // Categorize into domestic, commercial, or international
        $typeStr = strtolower($lead['move_type']);
        $lead['lead_type'] = 'domestic';
        if (str_contains($typeStr, 'commercial') || str_contains($typeStr, 'office') || str_contains($typeStr, 'business')) {
            $lead['lead_type'] = 'commercial';
        } elseif (str_contains($typeStr, 'international') || str_contains($typeStr, 'abroad') || str_contains($typeStr, 'overseas') || str_contains($typeStr, 'country')) {
            $lead['lead_type'] = 'international';
        }

        // Normalise the move date to Y-m-d; default 30 days out if unparseable.
        $lead['move_date'] = $this->normaliseDate($lead['move_date'] ?? null);

        // Normalise estimated value to a number.
        $lead['est_value'] = $this->normaliseMoney($lead['est_value'] ?? null);

        return $lead;
    }

    /**
     * Decide which provider (if any) an email belongs to.
     *
     * Domain matches take priority over subject-keyword matches across ALL
     * providers, so a generic subject ("new lead") never wins over an exact
     * sender-domain match for a different site.
     */
    private function matchProvider(string $fromEmail, string $subject, string $bodyHtml = ''): ?array
    {
        $domain  = substr(strrchr($fromEmail, '@') ?: '', 1);
        $subject = strtolower($subject);

        // Ignore automated welcome emails or sent messages
        if (str_contains(strtolower($subject), 'welcome to next gen relocation')) {
            return null;
        }

        // Pass 1: sender-domain match (authoritative for known lead provider portals like PinLocal, CompareMyMove, etc.).
        foreach ($this->providers() as $provider) {
            if (in_array($provider['source'], ['PinLocal', 'CompareMyMove', 'reallymoving', 'Getamover', 'Konnectyou'])) {
                foreach ($provider['match']['domains'] ?? [] as $d) {
                    $d = strtolower($d);
                    if ($domain === $d || str_ends_with($domain, '.' . $d)) {
                        return $provider;
                    }
                }
            }
        }

        // Pass 1.5: Forwarded email detection — check if a known provider domain
        // appears in the subject or body (e.g. "Fwd: Removals lead from Exzil (konnectyou.com)").
        // This catches emails forwarded by partners from their own inbox.
        $bodyLower = strtolower($bodyHtml);
        foreach ($this->providers() as $provider) {
            if (in_array($provider['source'], ['PinLocal', 'CompareMyMove', 'reallymoving', 'Getamover', 'Konnectyou'])) {
                foreach ($provider['match']['domains'] ?? [] as $d) {
                    $d = strtolower($d);
                    if (str_contains($subject, $d) || str_contains($bodyLower, '@' . $d)) {
                        return $provider;
                    }
                }
            }
        }

        // Pass 2: subject-keyword match for direct customer emails containing lead/enquiry/move keywords.
        foreach ($this->providers() as $provider) {
            foreach ($provider['match']['subjects'] ?? [] as $kw) {
                if ($kw !== '' && str_contains($subject, strtolower($kw))) {
                    return $provider;
                }
            }
        }

        // Pass 3: Fallback match - only match if the body contains strong moving indicators, otherwise return null.
        if (preg_match('/(moving from|moving to|move size|bedrooms)/i', $bodyHtml)) {
            return $this->providers()[0];
        }

        return null;
    }

    /**
     * Convert HTML email body to reasonably clean plain text.
     */
    private function htmlToText(string $html): string
    {
        // Turn block tags and <br> into newlines so label/value pairs survive.
        $html = preg_replace('/<\s*br\s*\/?>/i', "\n", $html);
        $html = preg_replace('/<\/\s*(p|div|li|h[1-6])\s*>/i', "\n", $html);
        // A table row is one logical line; the first cell boundary inside it
        // becomes the label/value separator. End of row => newline.
        $html = preg_replace('/<\/\s*tr\s*>/i', "\n", $html);
        // Separate the label cell from the value cell with a colon, but only
        // between cells (a closing cell followed by an opening cell).
        $html = preg_replace('/<\/\s*t[dh]\s*>\s*<\s*t[dh][^>]*>/i', ' : ', $html);
        $text = strip_tags($html);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Collapse runs of whitespace but keep line breaks.
        $text = preg_replace('/[ \t]+/', ' ', $text);
        $text = preg_replace('/\n\s*\n+/', "\n", $text);

        return trim($text);
    }

    /**
     * Extract "label => value" pairs from plain-text body.
     *
     * Handles "Label: value", "Label - value", and table-style "Label : value".
     */
    private function extractPairs(string $text): array
    {
        $pairs = [];
        foreach (preg_split('/\r?\n/', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^(.{2,40}?)\s*[:\-]\s*(.+)$/u', $line, $m)) {
                $label = strtolower(trim($m[1]));
                $value = ltrim(trim($m[2]), ' :-');
                // Skip obvious non-labels (e.g. URLs, times like "12:30").
                if ($label !== '' && $value !== '' && !preg_match('/^https?/i', $label)) {
                    // Keep the first occurrence of a label.
                    if (!isset($pairs[$label])) {
                        $pairs[$label] = $value;
                    }
                }
            }
        }

        return $pairs;
    }

    /**
     * Given the extracted pairs, return the first value whose label matches
     * one of the provided aliases (exact, then contains).
     */
    private function pickValue(array $pairs, array $aliases): ?string
    {
        foreach ($aliases as $alias) {
            $alias = strtolower($alias);
            if (isset($pairs[$alias])) {
                return $pairs[$alias];
            }
        }
        // Looser: label contains the alias word.
        foreach ($aliases as $alias) {
            $alias = strtolower($alias);
            foreach ($pairs as $label => $value) {
                if (str_contains($label, $alias)) {
                    return $value;
                }
            }
        }

        return null;
    }

    /**
     * Normalise an arbitrary date string to Y-m-d.
     */
    private function normaliseDate(?string $raw): string
    {
        if ($raw) {
            try {
                return Carbon::parse($raw)->format('Y-m-d');
            } catch (\Throwable $e) {
                // fall through to default
            }
        }

        return Carbon::now()->addDays(30)->format('Y-m-d');
    }

    /**
     * Strip currency symbols / commas and return a numeric value.
     */
    private function normaliseMoney(?string $raw): float
    {
        if (!$raw) {
            return 0.0;
        }
        $clean = preg_replace('/[^0-9.]/', '', $raw);

        return $clean === '' ? 0.0 : (float) $clean;
    }

    private function parseGetamover(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);
        $lead = [];
        $context = null;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $lower = strtolower($line);
            if (str_starts_with($lower, 'moving from')) {
                $context = 'from';
                continue;
            } elseif (str_starts_with($lower, 'moving to')) {
                $context = 'to';
                continue;
            } elseif (str_starts_with($lower, 'contact information')) {
                $context = 'contact';
                continue;
            } elseif (str_starts_with($lower, 'details')) {
                $context = 'details';
                continue;
            }

            if (preg_match('/^(.{2,40}?)\s*[:\-]\s*(.+)$/u', $line, $m)) {
                $label = strtolower(trim($m[1]));
                $value = ltrim(trim($m[2]), ' :-');

                if ($label === 'name') $lead['name'] = $value;
                elseif ($label === 'telephone' || $label === 'phone') $lead['phone'] = $value;
                elseif ($label === 'email') $lead['email'] = $value;
                elseif ($label === 'date' || $label === 'potential moving date') $lead['move_date'] = $value;
                elseif ($label === 'type' || $label === 'category') $lead['move_type'] = $value;
                elseif ($context === 'from' && in_array($label, ['address', 'city', 'postcode'])) {
                    $lead['from_location'] = isset($lead['from_location']) ? $lead['from_location'] . ', ' . $value : $value;
                }
                elseif ($context === 'to' && in_array($label, ['address', 'city', 'postcode'])) {
                    $lead['to_location'] = isset($lead['to_location']) ? $lead['to_location'] . ', ' . $value : $value;
                }
            }
        }
        return $lead;
    }

    private function parseKonnectyou(string $text): array
    {
        $lines = preg_split('/\r?\n/', $text);
        $lead = [];
        $context = null;
        $contextLineCount = 0;

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            $lower = strtolower($line);
            
            // Context Switchers
            if (str_starts_with($lower, 'contact details')) {
                $context = 'contact';
                $contextLineCount = 0;
                continue;
            } elseif (str_starts_with($lower, 'moving date')) {
                $context = 'date';
                $contextLineCount = 0;
                continue;
            } elseif (str_starts_with($lower, 'current address')) {
                $context = 'from';
                $contextLineCount = 0;
                continue;
            } elseif (str_starts_with($lower, 'new address')) {
                $context = 'to';
                $contextLineCount = 0;
                continue;
            } elseif (str_starts_with($lower, 'additional services') || str_starts_with($lower, 'additional information') || str_starts_with($lower, 'need help')) {
                $context = 'other';
                continue;
            }

            // Context Parsing
            if ($context === 'contact') {
                if ($contextLineCount === 0) $lead['name'] = $line;
                elseif ($contextLineCount === 1) $lead['phone'] = $line;
                elseif ($contextLineCount === 2) $lead['email'] = $line;
                $contextLineCount++;
            } elseif ($context === 'date') {
                if ($contextLineCount === 0 && !str_starts_with($lower, 'flexible:')) {
                    $lead['move_date'] = $line;
                }
                $contextLineCount++;
            } elseif ($context === 'from') {
                if (preg_match('/^(bedrooms|home type):/i', $line)) {
                    $lead['move_type'] = isset($lead['move_type']) ? $lead['move_type'] . ' - ' . $line : $line;
                } else {
                    $lead['from_location'] = isset($lead['from_location']) ? $lead['from_location'] . ', ' . $line : $line;
                }
            } elseif ($context === 'to') {
                if (preg_match('/^(bedrooms|home type):/i', $line)) {
                    // ignoring to property type as it's usually same
                } else {
                    $lead['to_location'] = isset($lead['to_location']) ? $lead['to_location'] . ', ' . $line : $line;
                }
            }
        }
        return $lead;
    }
}
