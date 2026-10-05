import { useState } from "react";
import {
  Truck,
  Box,
  Layers,
  Package,
  ArrowUpRight,
  ShieldAlert,
  Wrench,
  Sparkles,
  FileText,
  CheckCircle2,
  XCircle,
  HelpCircle,
  MessageSquare,
  ClipboardList,
  ChevronDown,
  ChevronUp,
} from "lucide-react";

interface SurveyReportViewProps {
  reportNotes?: string | null;
  propertyType?: string | null;
  cargoVolume?: string | null;
  totalBoxes?: string | null;
  recommendedVehicle?: string | null;
  packingService?: string | null;
  accessOrigin?: string | null;
  liftAvailable?: boolean | null;
  parkingAvailable?: boolean | null;
  customAccessFields?: any[];
  requiresDisassembly?: boolean | null;
  bedsQuantity?: number | null;
  wardrobesQuantity?: number | null;
  customDismantleItems?: any[];
  hasFragileItems?: boolean | null;
  fineArtPaintings?: boolean | null;
  pianoAntiqueItems?: boolean | null;
  customFragileFields?: any[];
  specialInstructions?: string | null;
  surveyorFindings?: string | null;
}

export function SurveyReportView(props: SurveyReportViewProps) {
  const [showRawText, setShowRawText] = useState(false);

  // Parse raw text fallback if structured props aren't fully set
  const rawText = props.reportNotes || "";
  
  // Extract values from rawText if structured props are empty
  const getValueFromRaw = (label: string): string | null => {
    const match = rawText.match(new RegExp(`${label}:\\s*(.+)`, "i"));
    return match ? match[1].trim() : null;
  };

  const moveCategory = props.propertyType || getValueFromRaw("Move Category") || getValueFromRaw("Move Type / Property Category") || "Domestic";
  const cargoVol = props.cargoVolume || getValueFromRaw("Estimated Cargo Volume") || "Not specified";
  const boxes = props.totalBoxes || getValueFromRaw("Number of Boxes / Cartons") || "Not specified";
  const vehicle = props.recommendedVehicle || getValueFromRaw("Recommended Vehicle") || "TBD";
  const packing = props.packingService || getValueFromRaw("Packing Service") || "Standard Packing";
  const originAccess = props.accessOrigin || getValueFromRaw("Origin Access") || "Ground Floor";

  const liftAvail = props.liftAvailable !== undefined && props.liftAvailable !== null
    ? props.liftAvailable
    : rawText.toLowerCase().includes("lift available: available");

  const parkingAvail = props.parkingAvailable !== undefined && props.parkingAvailable !== null
    ? props.parkingAvailable
    : rawText.toLowerCase().includes("parking available: available");

  // Dismantling items
  const beds = props.bedsQuantity ?? (rawText.match(/Beds:\s*(\d+)/i)?.[1] ? parseInt(rawText.match(/Beds:\s*(\d+)/i)![1]) : 0);
  const wardrobes = props.wardrobesQuantity ?? (rawText.match(/Wardrobes:\s*(\d+)/i)?.[1] ? parseInt(rawText.match(/Wardrobes:\s*(\d+)/i)![1]) : 0);
  const dismantleItems = props.customDismantleItems || [];
  const requiresDismantle = props.requiresDisassembly ?? (beds > 0 || wardrobes > 0 || dismantleItems.length > 0 || rawText.includes("DISMANTLING & REASSEMBLY REQUIRED:\nYES"));

  // Fragile items
  const hasFineArt = props.fineArtPaintings ?? rawText.toLowerCase().includes("fine art");
  const hasPiano = props.pianoAntiqueItems ?? rawText.toLowerCase().includes("piano");
  const fragileItems = props.customFragileFields || [];
  const isFragile = props.hasFragileItems ?? (hasFineArt || hasPiano || fragileItems.length > 0 || rawText.includes("FRAGILE / HIGH-VALUE ITEMS:\nYES"));

  const specialInstructions = props.specialInstructions || (rawText.match(/CUSTOMER SPECIAL INSTRUCTIONS:\s*\n([^\n=]+)/i)?.[1]?.trim()) || null;
  
  // Clean findings
  let findings = props.surveyorFindings || (rawText.match(/SURVEYOR INSPECTION FINDINGS & ROOM INVENTORY:\s*\n([\s\S]+?)(?=\n={5,}|$)/i)?.[1]?.trim()) || null;
  if (findings) {
    findings = findings.replace(/={10,}[\s\S]*?={10,}/g, "").replace(/={10,}/g, "").trim();
  }

  // Render raw text with structured syntax highlighting if user toggles raw view
  const formattedSections = parseRawSections(rawText);

  return (
    <div className="space-y-4">
      {/* ── 1. Top Summary Metric Cards ── */}
      <div className="grid grid-cols-2 sm:grid-cols-4 gap-2.5">
        <div className="rounded-xl border border-gold/20 bg-gold/5 p-3 flex flex-col justify-between">
          <div className="flex items-center justify-between text-gold text-[10px] uppercase font-bold tracking-wider mb-1">
            <span>Move Category</span>
            <Layers className="h-3.5 w-3.5" />
          </div>
          <div className="text-sm font-bold text-foreground truncate">{moveCategory}</div>
        </div>

        <div className="rounded-xl border border-cyan-500/20 bg-cyan-500/5 p-3 flex flex-col justify-between">
          <div className="flex items-center justify-between text-cyan-400 text-[10px] uppercase font-bold tracking-wider mb-1">
            <span>Cargo Volume</span>
            <Box className="h-3.5 w-3.5" />
          </div>
          <div className="text-sm font-bold text-foreground truncate">{cargoVol}</div>
        </div>

        <div className="rounded-xl border border-purple-500/20 bg-purple-500/5 p-3 flex flex-col justify-between">
          <div className="flex items-center justify-between text-purple-400 text-[10px] uppercase font-bold tracking-wider mb-1">
            <span>Total Boxes</span>
            <Package className="h-3.5 w-3.5" />
          </div>
          <div className="text-sm font-bold text-foreground truncate">{boxes}</div>
        </div>

        <div className="rounded-xl border border-emerald-500/20 bg-emerald-500/5 p-3 flex flex-col justify-between">
          <div className="flex items-center justify-between text-emerald-400 text-[10px] uppercase font-bold tracking-wider mb-1">
            <span>Recommended Vehicle</span>
            <Truck className="h-3.5 w-3.5" />
          </div>
          <div className="text-sm font-bold text-foreground truncate">{vehicle}</div>
        </div>
      </div>

      {/* ── 2. Access & Logistics Breakdown ── */}
      <div className="rounded-xl border border-border/40 bg-card/40 p-4 space-y-3">
        <div className="flex items-center gap-2 text-xs font-bold uppercase tracking-wider text-gold">
          <ArrowUpRight className="h-4 w-4" />
          <span>Access & Logistics Specification</span>
        </div>

        <div className="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
          <div className="flex items-center justify-between p-2.5 rounded-lg bg-background/50 border border-border/30">
            <span className="text-muted-foreground">Packing Service:</span>
            <span className="font-semibold text-foreground bg-gold/15 text-gold border border-gold/30 px-2 py-0.5 rounded text-[11px]">
              {packing}
            </span>
          </div>

          <div className="flex items-center justify-between p-2.5 rounded-lg bg-background/50 border border-border/30">
            <span className="text-muted-foreground">Origin Access:</span>
            <span className="font-semibold text-foreground">{originAccess}</span>
          </div>

          <div className="flex items-center justify-between p-2.5 rounded-lg bg-background/50 border border-border/30">
            <span className="text-muted-foreground">Lift / Elevator:</span>
            {liftAvail ? (
              <span className="inline-flex items-center gap-1 text-emerald-400 font-semibold bg-emerald-500/10 border border-emerald-500/30 px-2 py-0.5 rounded text-[11px]">
                <CheckCircle2 className="h-3 w-3" /> Available
              </span>
            ) : (
              <span className="inline-flex items-center gap-1 text-rose-400 font-semibold bg-rose-500/10 border border-rose-500/30 px-2 py-0.5 rounded text-[11px]">
                <XCircle className="h-3 w-3" /> Not Available
              </span>
            )}
          </div>

          <div className="flex items-center justify-between p-2.5 rounded-lg bg-background/50 border border-border/30">
            <span className="text-muted-foreground">Parking Bay Access:</span>
            {parkingAvail ? (
              <span className="inline-flex items-center gap-1 text-emerald-400 font-semibold bg-emerald-500/10 border border-emerald-500/30 px-2 py-0.5 rounded text-[11px]">
                <CheckCircle2 className="h-3 w-3" /> Available
              </span>
            ) : (
              <span className="inline-flex items-center gap-1 text-amber-400 font-semibold bg-amber-500/10 border border-amber-500/30 px-2 py-0.5 rounded text-[11px]">
                <HelpCircle className="h-3 w-3" /> Permit Required / Restricted
              </span>
            )}
          </div>
        </div>
      </div>

      {/* ── 3. Dismantling & Fragile Items Cards ── */}
      <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        {/* Dismantling */}
        <div className="rounded-xl border border-amber-500/20 bg-amber-500/5 p-4 space-y-2">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2 text-xs font-bold text-amber-400 uppercase tracking-wider">
              <Wrench className="h-4 w-4" />
              <span>Dismantling & Reassembly</span>
            </div>
            <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${requiresDismantle ? "bg-amber-500/20 text-amber-400 border border-amber-500/40" : "bg-muted/30 text-muted-foreground"}`}>
              {requiresDismantle ? "REQUIRED" : "NOT REQUIRED"}
            </span>
          </div>

          {requiresDismantle ? (
            <div className="flex flex-wrap gap-1.5 pt-1">
              {beds > 0 && (
                <span className="px-2.5 py-1 rounded-md bg-amber-500/10 text-amber-300 border border-amber-500/20 text-xs font-medium">
                  🛏️ Beds: {beds}
                </span>
              )}
              {wardrobes > 0 && (
                <span className="px-2.5 py-1 rounded-md bg-amber-500/10 text-amber-300 border border-amber-500/20 text-xs font-medium">
                  🚪 Wardrobes: {wardrobes}
                </span>
              )}
              {dismantleItems.map((it, idx) => (
                <span key={idx} className="px-2.5 py-1 rounded-md bg-amber-500/10 text-amber-300 border border-amber-500/20 text-xs font-medium">
                  ⚙️ {it.name || it.label}: {it.quantity || it.qty || 1}
                </span>
              ))}
            </div>
          ) : (
            <p className="text-xs text-muted-foreground pt-1">No furniture dismantling required.</p>
          )}
        </div>

        {/* Fragile Items */}
        <div className="rounded-xl border border-rose-500/20 bg-rose-500/5 p-4 space-y-2">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-2 text-xs font-bold text-rose-400 uppercase tracking-wider">
              <ShieldAlert className="h-4 w-4" />
              <span>Fragile & High-Value Items</span>
            </div>
            <span className={`px-2 py-0.5 rounded text-[10px] font-bold ${isFragile ? "bg-rose-500/20 text-rose-400 border border-rose-500/40" : "bg-muted/30 text-muted-foreground"}`}>
              {isFragile ? "HIGH VALUE" : "STANDARD"}
            </span>
          </div>

          {isFragile ? (
            <div className="flex flex-wrap gap-1.5 pt-1">
              {hasFineArt && (
                <span className="px-2.5 py-1 rounded-md bg-rose-500/10 text-rose-300 border border-rose-500/20 text-xs font-medium">
                  🖼️ Fine Art / Paintings
                </span>
              )}
              {hasPiano && (
                <span className="px-2.5 py-1 rounded-md bg-rose-500/10 text-rose-300 border border-rose-500/20 text-xs font-medium">
                  🎹 Piano / Antiques
                </span>
              )}
              {fragileItems.map((f, idx) => (
                <span key={idx} className="px-2.5 py-1 rounded-md bg-rose-500/10 text-rose-300 border border-rose-500/20 text-xs font-medium">
                  ✨ {f.label || f.name}
                </span>
              ))}
            </div>
          ) : (
            <p className="text-xs text-muted-foreground pt-1">No special fragile wrapping reported.</p>
          )}
        </div>
      </div>

      {/* ── 4. Customer Special Instructions ── */}
      {specialInstructions && (
        <div className="rounded-xl border border-amber-500/30 bg-amber-500/10 p-3.5 space-y-1">
          <div className="flex items-center gap-2 text-xs font-bold text-amber-400">
            <MessageSquare className="h-4 w-4" />
            <span>Customer Special Instructions</span>
          </div>
          <p className="text-xs text-amber-200 leading-relaxed pl-6">{specialInstructions}</p>
        </div>
      )}

      {/* ── 5. Surveyor Findings & Inventory ── */}
      {findings && (
        <div className="rounded-xl border border-gold/25 bg-gold/5 p-4 space-y-2">
          <div className="flex items-center gap-2 text-xs font-bold text-gold uppercase tracking-wider">
            <ClipboardList className="h-4 w-4" />
            <span>Surveyor Inspection Findings & Room Inventory</span>
          </div>
          <div className="text-xs text-foreground/90 leading-relaxed whitespace-pre-wrap pl-6 border-l-2 border-gold/30">
            {findings}
          </div>
        </div>
      )}

      {/* ── 6. Collapsible Raw Text / Formatted Notes Toggle ── */}
      {rawText && (
        <div className="pt-2">
          <button
            onClick={() => setShowRawText(!showRawText)}
            className="inline-flex items-center gap-1.5 text-xs text-muted-foreground hover:text-gold transition-colors font-medium"
          >
            <FileText className="h-3.5 w-3.5" />
            {showRawText ? "Hide Formatted Text Report" : "View Full Formatted Text Report"}
            {showRawText ? <ChevronUp className="h-3.5 w-3.5" /> : <ChevronDown className="h-3.5 w-3.5" />}
          </button>

          {showRawText && (
            <div className="mt-3 rounded-xl border border-border/50 bg-background/80 p-4 space-y-3 font-mono text-xs">
              {formattedSections.map((sec, idx) => (
                <div key={idx} className={sec.isHeader ? "text-gold font-bold border-b border-gold/20 pb-1 mt-2 text-xs uppercase" : "text-muted-foreground leading-relaxed"}>
                  {sec.text}
                </div>
              ))}
            </div>
          )}
        </div>
      )}
    </div>
  );
}

// Helper to format raw text into clean sections
function parseRawSections(text: string) {
  if (!text) return [];
  const lines = text.split(/\r?\n/);
  const result: { text: string; isHeader: boolean }[] = [];

  for (let line of lines) {
    line = line.trim();
    if (!line || line.match(/^={5,}$/)) continue;

    const isHeader = !!line.match(/^(HOUSE RELOCATION|DISMANTLING|FRAGILE|CUSTOMER SPECIAL|SURVEYOR INSPECTION|Section [A-G])/i);
    result.push({ text: line, isHeader });
  }

  return result;
}
