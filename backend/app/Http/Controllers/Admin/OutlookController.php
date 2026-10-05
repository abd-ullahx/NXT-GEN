<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Email;
use App\Models\OutlookToken;
use App\Services\OutlookService;
use Illuminate\Http\Request;

class OutlookController extends Controller
{
    private OutlookService $outlook;

    public function __construct(OutlookService $outlook)
    {
        $this->outlook = $outlook;
    }

    /**
     * Show the Outlook inbox page.
     */
    public function index(Request $request)
    {
        $status = $this->outlook->getConnectionStatus();

        $filter = $request->query('filter', 'all');
        $search = $request->query('search', '');

        $query = Email::query()->orderBy('received_at', 'desc');

        if ($filter === 'inbound') {
            $query->where('direction', 'inbound');
        } elseif ($filter === 'outbound') {
            $query->where('direction', 'outbound');
        } elseif ($filter === 'unread') {
            $query->where('is_read', false);
        }

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'LIKE', "%{$search}%")
                  ->orWhere('from_email', 'LIKE', "%{$search}%")
                  ->orWhere('from_name', 'LIKE', "%{$search}%")
                  ->orWhere('body_preview', 'LIKE', "%{$search}%");
            });
        }

        $emails = $query->paginate(30);
        $unreadCount = Email::where('is_read', false)->where('direction', 'inbound')->count();
        $selectedId = $request->query('email');
        $selectedEmail = $selectedId ? Email::find($selectedId) : null;

        return view('admin.outlook.index', compact(
            'status', 'emails', 'filter', 'search', 'unreadCount', 'selectedEmail'
        ));
    }

    /**
     * Redirect to Microsoft OAuth login.
     */
    public function connect()
    {
        try {
            $url = $this->outlook->getAuthUrl();
            return redirect($url);
        } catch (\Exception $e) {
            return redirect()->route('admin.outlook.index')
                ->with('error', 'Failed to start connection: ' . $e->getMessage());
        }
    }

    /**
     * Handle OAuth callback.
     */
    public function callback(Request $request)
    {
        $code = $request->query('code');

        if (!$code) {
            $error = $request->query('error_description', 'Authorization failed');
            return redirect()->route('admin.outlook.index')
                ->with('error', $error);
        }

        try {
            $this->outlook->exchangeCodeForTokens($code);
            $this->outlook->syncEmails(30);

            return redirect()->route('admin.outlook.index')
                ->with('success', 'Outlook connected and emails synced successfully!');
        } catch (\Exception $e) {
            return redirect()->route('admin.outlook.index')
                ->with('error', 'Connection failed: ' . $e->getMessage());
        }
    }

    /**
     * Disconnect Outlook.
     */
    public function disconnect()
    {
        $this->outlook->disconnect();
        return redirect()->route('admin.outlook.index')
            ->with('success', 'Outlook disconnected.');
    }

    /**
     * Trigger a manual sync.
     */
    public function sync()
    {
        try {
            $count = $this->outlook->syncEmails(50);
            return redirect()->route('admin.outlook.index')
                ->with('success', "{$count} new emails synced from Outlook.");
        } catch (\Exception $e) {
            return redirect()->route('admin.outlook.index')
                ->with('error', 'Sync failed: ' . $e->getMessage());
        }
    }

    /**
     * Send an email via Outlook.
     */
    public function send(Request $request)
    {
        $request->validate([
            'to'      => 'required|email',
            'subject' => 'required|string|max:500',
            'body'    => 'required|string',
        ]);

        try {
            $this->outlook->sendEmail(
                $request->input('to'),
                $request->input('subject'),
                nl2br(e($request->input('body')))
            );

            return redirect()->route('admin.outlook.index')
                ->with('success', 'Email sent successfully!');
        } catch (\Exception $e) {
            return redirect()->route('admin.outlook.index')
                ->with('error', 'Failed to send: ' . $e->getMessage());
        }
    }
}
