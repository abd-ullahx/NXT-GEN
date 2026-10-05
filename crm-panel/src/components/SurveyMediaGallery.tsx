import { useEffect, useState, useCallback } from "react";
import { useConfirm } from "@/contexts/ConfirmContext";
import {
  X,
  ChevronLeft,
  ChevronRight,
  ZoomIn,
  RefreshCw,
  Camera,
  Layers,
  User,
  Image as ImageIcon,
  Video,
  StickyNote,
  Trash2,
  Play,
  FileText,
  Upload,
  Download,
  ExternalLink,
  Plus,
  FileCheck
} from "lucide-react";
import { fetchSurveyMedia, deleteSurveyMedia, uploadSurveyMedia, type SurveyMediaItem } from "@/lib/api";
import { SurveyReportView } from "@/components/SurveyReportView";

export interface GallerySurveyInfo {
  id: string;
  clientName: string;
  clientEmail: string;
  clientPhone: string;
  surveyNumber: string;
  location: string;
  moveType: string;
  surveyDate: string;
  surveyTime: string;
  surveyorReportNotes?: string;
  surveyorMedia?: string[];
}

// ─── Media Gallery Lightbox ───────────────────────────────────────────────────
export function MediaLightbox({
  items,
  startIndex,
  onClose,
}: {
  items: SurveyMediaItem[];
  startIndex: number;
  onClose: () => void;
}) {
  const [idx, setIdx] = useState(startIndex);
  const item = items[idx];
  const prev = () => setIdx((i) => Math.max(0, i - 1));
  const next = () => setIdx((i) => Math.min(items.length - 1, i + 1));

  useEffect(() => {
    const handler = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
      if (e.key === "ArrowLeft") prev();
      if (e.key === "ArrowRight") next();
    };
    window.addEventListener("keydown", handler);
    return () => window.removeEventListener("keydown", handler);
  }, [onClose]);

  if (!item) return null;

  return (
    <div
      className="fixed inset-0 z-[999999] flex items-center justify-center bg-black/95 backdrop-blur-xl"
      onClick={onClose}
    >
      <div
        className="relative max-w-5xl w-full mx-4 flex flex-col items-center"
        onClick={(e) => e.stopPropagation()}
      >
        <button
          onClick={onClose}
          className="absolute -top-10 right-0 text-white/60 hover:text-white transition-colors"
        >
          <X className="h-7 w-7" />
        </button>
        <div className="absolute -top-10 left-0 text-xs text-white/50 font-mono">
          {idx + 1} / {items.length}
        </div>
        <div className="w-full max-h-[75vh] flex items-center justify-center bg-black rounded-2xl overflow-hidden border border-white/10">
          {item.type === "image" && item.fileUrl ? (
            <img
              src={item.fileUrl}
              alt={item.caption || "Survey photo"}
              className="max-w-full max-h-[75vh] object-contain"
            />
          ) : item.type === "video" && item.fileUrl ? (
            <video
              src={item.fileUrl}
              controls
              autoPlay
              className="max-w-full max-h-[75vh]"
            />
          ) : item.type === "document" && item.fileUrl ? (
            <div className="p-8 text-center space-y-4">
              <FileText className="h-16 w-16 text-gold mx-auto" />
              <div>
                <p className="text-white text-base font-semibold">{item.caption || item.fileName || "Document File"}</p>
                <p className="text-white/60 text-xs mt-1">{item.fileName}</p>
              </div>
              <div className="flex items-center justify-center gap-3 pt-2">
                <a
                  href={item.fileUrl}
                  target="_blank"
                  rel="noopener noreferrer"
                  className="flex items-center gap-2 px-4 py-2 rounded-xl bg-gold text-black font-semibold text-xs hover:bg-gold/90 transition-all"
                >
                  <ExternalLink className="h-4 w-4" /> Open / Preview
                </a>
                <a
                  href={item.fileUrl}
                  download={item.fileName || "document"}
                  className="flex items-center gap-2 px-4 py-2 rounded-xl border border-white/30 text-white font-semibold text-xs hover:bg-white/10 transition-all"
                >
                  <Download className="h-4 w-4" /> Download
                </a>
              </div>
            </div>
          ) : (
            <div className="p-8 text-center space-y-3">
              <StickyNote className="h-12 w-12 text-gold mx-auto" />
              <p className="text-white text-sm font-medium">
                {item.notes || "No content"}
              </p>
            </div>
          )}
        </div>
        <div className="mt-3 text-center space-y-1">
          {item.caption && (
            <p className="text-white/80 text-sm font-medium">{item.caption}</p>
          )}
          {item.notes && item.type !== "note" && (
            <p className="text-white/50 text-xs">{item.notes}</p>
          )}
          <p className="text-white/30 text-[11px] font-mono">
            By {item.surveyorName || "Surveyor"} ·{" "}
            {item.createdAt ? new Date(item.createdAt).toLocaleString() : ""}
          </p>
        </div>
        {items.length > 1 && (
          <>
            <button
              onClick={prev}
              disabled={idx === 0}
              className="absolute left-0 top-1/2 -translate-y-1/2 -translate-x-12 p-2 rounded-full bg-white/10 hover:bg-white/20 disabled:opacity-30 transition-all text-white"
            >
              <ChevronLeft className="h-6 w-6" />
            </button>
            <button
              onClick={next}
              disabled={idx === items.length - 1}
              className="absolute right-0 top-1/2 -translate-y-1/2 translate-x-12 p-2 rounded-full bg-white/10 hover:bg-white/20 disabled:opacity-30 transition-all text-white"
            >
              <ChevronRight className="h-6 w-6" />
            </button>
          </>
        )}
      </div>
    </div>
  );
}

// ─── Survey Media Gallery Panel ───────────────────────────────────────────────
export function SurveyMediaGallery({
  survey,
  onClose,
  readOnly = false,
}: {
  survey: GallerySurveyInfo;
  onClose: () => void;
  readOnly?: boolean;
}) {
  const confirm = useConfirm();
  const [media, setMedia] = useState<SurveyMediaItem[]>([]);
  const [loading, setLoading] = useState(true);
  const [activeFilter, setActiveFilter] = useState<"all" | "image" | "video" | "document" | "note">("all");
  const [lightboxIdx, setLightboxIdx] = useState<number | null>(null);
  const [deleting, setDeleting] = useState<number | null>(null);

  // Upload modal state
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [uploadFile, setUploadFile] = useState<File | null>(null);
  const [uploadType, setUploadType] = useState<"image" | "video" | "document" | "note">("document");
  const [uploadCaption, setUploadCaption] = useState("");
  const [uploadNotes, setUploadNotes] = useState("");
  const [isUploading, setIsUploading] = useState(false);

    const load = useCallback(async () => {
    setLoading(true);
    try {
      const data = await fetchSurveyMedia(survey.id);
      const items = Array.isArray(data) ? data : [];
      
      // Inject web portal media (if any, deduplicated against fetched items)
      if (survey.surveyorMedia && survey.surveyorMedia.length > 0) {
          const existingUrls = new Set(items.map(it => it.fileUrl));
          survey.surveyorMedia.forEach((url, i) => {
              if (url && !existingUrls.has(url)) {
                  existingUrls.add(url);
                  const isVideo = url.startsWith("data:video") || !!url.match(/\.(mp4|webm|mov)$/i);
                  items.push({
                      id: -(i + 1), // fake negative ID for rendering
                      leadId: survey.id,
                      type: isVideo ? "video" : "image",
                      fileUrl: url,
                      fileName: "Portal Upload",
                      fileSize: null,
                      mimeType: null,
                      notes: null,
                      caption: "Web Portal Upload",
                      surveyorName: "Surveyor (Web Portal)",
                      createdAt: new Date().toISOString(),
                  } as SurveyMediaItem);
              }
          });
      }
      setMedia(items);
    } catch {
      setMedia([]);
    } finally {
      setLoading(false);
    }
  }, [survey.id, survey.surveyorMedia]);

  useEffect(() => { load(); }, [load]);

  const handleDelete = async (id: number) => {
    if (!await confirm("Delete this media item?")) return;
    setDeleting(id);
    try {
      await deleteSurveyMedia(id);
      setMedia((prev) => prev.filter((m) => m.id !== id));
    } catch {}
    setDeleting(null);
  };

  const filtered = activeFilter === "all" ? media : media.filter((m) => m.type === activeFilter);
  const lightboxItems = filtered.filter((m) => m.type !== "note");

  const formatSize = (bytes: number | null) => {
    if (!bytes) return "";
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(0)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
  };

  const countByType = (t: string) => media.filter((m) => m.type === t).length;

  return (
    <>
      <div className="fixed inset-0 z-[9999] flex items-start justify-center bg-black/85 backdrop-blur-lg p-4 overflow-y-auto animate-in fade-in duration-200">
        <div className="glass-card w-full max-w-5xl rounded-3xl border border-gold/40 shadow-2xl bg-[#0A0B0E] mt-6 mb-6">
          <div className="p-5 border-b border-gold/20 flex items-center justify-between gap-4">
            <div className="flex items-center gap-3 min-w-0">
              <div className="p-2.5 rounded-xl bg-gold/20 border border-gold/30 text-gold shrink-0">
                <Camera className="h-5 w-5" />
              </div>
              <div className="min-w-0">
                <h2 className="font-display text-xl font-semibold text-foreground truncate">
                  Survey Media Gallery
                </h2>
                <p className="text-xs text-muted-foreground mt-0.5 truncate">
                  <strong className="text-gold">{survey.clientName}</strong> ·{" "}
                  {survey.surveyNumber} · Images, Videos & Notes from surveyor app
                </p>
              </div>
            </div>
            <div className="flex items-center gap-2 shrink-0">
              {!readOnly && (
                <button
                  onClick={() => setShowUploadModal(true)}
                  className="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-gold/20 border border-gold/40 text-gold hover:bg-gold/30 text-xs font-semibold transition-all"
                >
                  <Plus className="h-4 w-4" /> Upload Document / Media
                </button>
              )}
              <button
                onClick={load}
                className="p-2 rounded-xl border border-border/60 text-muted-foreground hover:text-gold transition-all"
                title="Refresh"
              >
                <RefreshCw className="h-4 w-4" />
              </button>
              <button
                onClick={onClose}
                className="p-2 rounded-xl border border-border/60 text-muted-foreground hover:text-foreground transition-all"
              >
                <X className="h-4 w-4" />
              </button>
            </div>
          </div>

          <div className="px-5 py-3 bg-card/30 border-b border-border/40 grid grid-cols-2 md:grid-cols-4 gap-4 text-xs">
            <div>
              <span className="text-muted-foreground text-[10px] uppercase tracking-wider font-bold block">Client</span>
              <span className="text-foreground font-semibold mt-0.5 block flex items-center gap-1">
                <User className="h-3 w-3 text-gold" /> {survey.clientName}
              </span>
            </div>
            <div>
              <span className="text-muted-foreground text-[10px] uppercase tracking-wider font-bold block">Contact</span>
              <span className="text-gold font-mono mt-0.5 block">{survey.clientPhone}</span>
              <span className="text-muted-foreground block">{survey.clientEmail}</span>
            </div>
            <div>
              <span className="text-muted-foreground text-[10px] uppercase tracking-wider font-bold block">Route</span>
              <span className="text-foreground font-semibold mt-0.5 block">{survey.location}</span>
              <span className="text-muted-foreground block">{survey.moveType}</span>
            </div>
            <div>
              <span className="text-muted-foreground text-[10px] uppercase tracking-wider font-bold block">Survey Date</span>
              <span className="text-gold font-semibold mt-0.5 block">{survey.surveyDate}</span>
              <span className="text-muted-foreground block">{survey.surveyTime}</span>
            </div>
          </div>

          {/* Upload Modal Form — admin (readOnly) cannot upload */}
          {!readOnly && showUploadModal && (
            <div className="p-5 border-b border-gold/30 bg-gold/5 animate-in slide-in-from-top duration-200">
              <div className="flex items-center justify-between mb-3">
                <h3 className="text-sm font-semibold text-gold flex items-center gap-2">
                  <Upload className="h-4 w-4" /> Upload Document, PDF, Photo or Video
                </h3>
                <button onClick={() => setShowUploadModal(false)} className="text-muted-foreground hover:text-foreground text-xs">Cancel</button>
              </div>
              <form
                onSubmit={async (e) => {
                  e.preventDefault();
                  if (uploadType !== "note" && !uploadFile) {
                    alert("Please select a file to upload");
                    return;
                  }
                  setIsUploading(true);
                  try {
                    const fd = new FormData();
                    fd.append("lead_id", survey.id);
                    fd.append("type", uploadType);
                    if (uploadFile) fd.append("file", uploadFile);
                    if (uploadCaption) fd.append("caption", uploadCaption);
                    if (uploadNotes) fd.append("notes", uploadNotes);
                    fd.append("surveyor_name", "CRM Admin");

                    await uploadSurveyMedia(fd);
                    setShowUploadModal(false);
                    setUploadFile(null);
                    setUploadCaption("");
                    setUploadNotes("");
                    await load();
                  } catch (err: any) {
                    alert("Upload failed: " + err.message);
                  } finally {
                    setIsUploading(false);
                  }
                }}
                className="grid grid-cols-1 md:grid-cols-3 gap-3 text-xs"
              >
                <div>
                  <label className="block text-[10px] uppercase tracking-wider text-muted-foreground mb-1 font-bold">Category</label>
                  <select
                    value={uploadType}
                    onChange={(e) => setUploadType(e.target.value as any)}
                    className="w-full rounded-xl border border-border/80 bg-black/60 px-3 py-2 text-foreground focus:border-gold outline-none"
                  >
                    <option value="document">Document (PDF, DOC, TXT, CSV)</option>
                    <option value="image">Photo / Image</option>
                    <option value="video">Video</option>
                    <option value="note">Text Note</option>
                  </select>
                </div>
                {uploadType !== "note" && (
                  <div>
                    <label className="block text-[10px] uppercase tracking-wider text-muted-foreground mb-1 font-bold">Select File</label>
                    <input
                      type="file"
                      onChange={(e) => setUploadFile(e.target.files?.[0] || null)}
                      accept={
                        uploadType === "document" ? ".pdf,.doc,.docx,.txt,.csv,.xls,.xlsx,.rtf,.odt" :
                        uploadType === "image" ? "image/*" :
                        uploadType === "video" ? "video/*" : "*"
                      }
                      className="w-full rounded-xl border border-border/80 bg-black/60 px-3 py-1.5 text-foreground file:mr-2 file:py-1 file:px-2 file:rounded-lg file:border-0 file:bg-gold/20 file:text-gold file:text-xs"
                    />
                  </div>
                )}
                <div>
                  <label className="block text-[10px] uppercase tracking-wider text-muted-foreground mb-1 font-bold">Title / Caption</label>
                  <input
                    type="text"
                    placeholder="e.g. Floor Plan PDF / Inventory List"
                    value={uploadCaption}
                    onChange={(e) => setUploadCaption(e.target.value)}
                    className="w-full rounded-xl border border-border/80 bg-black/60 px-3 py-2 text-foreground focus:border-gold outline-none"
                  />
                </div>
                <div className="md:col-span-3 flex justify-end gap-2 pt-1">
                  <button
                    type="button"
                    onClick={() => setShowUploadModal(false)}
                    className="px-4 py-2 rounded-xl border border-border/60 text-muted-foreground hover:text-foreground text-xs"
                  >
                    Cancel
                  </button>
                  <button
                    type="submit"
                    disabled={isUploading}
                    className="flex items-center gap-1.5 px-5 py-2 rounded-xl bg-gold text-black font-semibold text-xs hover:bg-gold/90 disabled:opacity-50 transition-all"
                  >
                    {isUploading ? "Uploading..." : "Upload Now"}
                  </button>
                </div>
              </form>
            </div>
          )}

          <div className="px-5 pt-4 pb-3 flex flex-wrap items-center justify-between gap-3">
            <div className="flex flex-wrap items-center gap-2">
              {(["all", "image", "video", "document", "note"] as const).map((f) => {
                const count = f === "all" ? media.length : countByType(f);
                const icon = f === "all" ? <Layers className="h-3.5 w-3.5" /> :
                  f === "image" ? <ImageIcon className="h-3.5 w-3.5" /> :
                  f === "video" ? <Video className="h-3.5 w-3.5" /> :
                  f === "document" ? <FileText className="h-3.5 w-3.5" /> :
                  <StickyNote className="h-3.5 w-3.5" />;
                return (
                  <button
                    key={f}
                    onClick={() => setActiveFilter(f)}
                    className={`flex items-center gap-1.5 px-3 py-1.5 rounded-full text-xs font-semibold border transition-all capitalize ${
                      activeFilter === f
                        ? "bg-gold/20 border-gold/50 text-gold"
                        : "border-border/60 text-muted-foreground hover:text-foreground"
                    }`}
                  >
                    {icon}
                    {f === "all" ? "All Media" : f === "document" ? "Documents" : f + "s"}
                    <span className={`ml-0.5 text-[10px] px-1.5 py-0.5 rounded-full font-mono ${
                      activeFilter === f ? "bg-gold/30 text-gold" : "bg-card text-muted-foreground"
                    }`}>{count}</span>
                  </button>
                );
              })}
            </div>
            <span className="text-xs text-muted-foreground">
              {loading ? "Loading..." : `${filtered.length} item${filtered.length !== 1 ? "s" : ""}`}
            </span>
          </div>

          <div className="p-5 pt-2 min-h-[300px]">
            {loading ? (
              <div className="flex flex-col items-center justify-center py-16 space-y-3">
                <div className="h-10 w-10 animate-spin rounded-full border-4 border-gold border-t-transparent" />
                <p className="text-sm text-muted-foreground">Loading survey media...</p>
              </div>
            ) : filtered.length === 0 ? (
              <div className="flex flex-col items-center justify-center py-16 space-y-3 text-center">
                <div className="h-16 w-16 rounded-2xl bg-card/60 border border-border/40 flex items-center justify-center">
                  <Camera className="h-8 w-8 text-muted-foreground/40" />
                </div>
                <div>
                  <p className="text-sm font-semibold text-muted-foreground">No {activeFilter === "all" ? "media" : activeFilter + "s"} uploaded yet</p>
                  <p className="text-xs text-muted-foreground mt-1">
                    The surveyor's app will upload photos, videos and notes here during the survey.
                  </p>
                </div>
              </div>
            ) : (
              <div className="space-y-6">
                {filtered.some((m) => m.type === "image") && (
                  <div>
                    <h3 className="text-xs font-bold uppercase tracking-widest text-gold flex items-center gap-2 mb-3">
                      <ImageIcon className="h-4 w-4" /> Photos ({countByType("image")})
                    </h3>
                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-3">
                      {filtered.filter((m) => m.type === "image").map((item) => {
                        const lbIdx = lightboxItems.findIndex((i) => i.id === item.id);
                        return (
                          <div key={item.id} className="group relative rounded-xl overflow-hidden border border-border/40 bg-black/60 aspect-square">
                            <img
                              src={item.fileUrl || ""}
                              alt={item.caption || "Survey photo"}
                              className="w-full h-full object-cover transition-transform group-hover:scale-105"
                            />
                            <div className="absolute inset-0 bg-black/0 group-hover:bg-black/50 transition-all flex flex-col items-center justify-center gap-2 opacity-0 group-hover:opacity-100">
                              <button
                                onClick={() => setLightboxIdx(lbIdx)}
                                className="p-2 rounded-full bg-white/20 text-white hover:bg-white/30 transition-all"
                              >
                                <ZoomIn className="h-4 w-4" />
                              </button>
                              <button
                                onClick={() => handleDelete(item.id)}
                                disabled={deleting === item.id}
                                className="p-2 rounded-full bg-red-500/20 text-red-400 hover:bg-red-500/40 transition-all"
                              >
                                <Trash2 className="h-4 w-4" />
                              </button>
                            </div>
                            {item.caption && (
                              <div className="absolute bottom-0 left-0 right-0 bg-black/70 text-white text-[10px] px-2 py-1 truncate opacity-0 group-hover:opacity-100 transition-opacity">
                                {item.caption}
                              </div>
                            )}
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}

                {filtered.some((m) => m.type === "video") && (
                  <div>
                    <h3 className="text-xs font-bold uppercase tracking-widest text-gold flex items-center gap-2 mb-3">
                      <Video className="h-4 w-4" /> Videos ({countByType("video")})
                    </h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
                      {filtered.filter((m) => m.type === "video").map((item) => {
                        const lbIdx = lightboxItems.findIndex((i) => i.id === item.id);
                        return (
                          <div key={item.id} className="group relative rounded-xl overflow-hidden border border-border/40 bg-black/60">
                            <video
                              src={item.fileUrl || ""}
                              className="w-full max-h-44 object-cover"
                              preload="metadata"
                            />
                            <div className="absolute inset-0 flex items-center justify-center bg-black/30 group-hover:bg-black/50 transition-all">
                              <button
                                onClick={() => setLightboxIdx(lbIdx)}
                                className="h-12 w-12 rounded-full bg-white/20 text-white hover:bg-gold/60 transition-all flex items-center justify-center border border-white/30"
                              >
                                <Play className="h-5 w-5 ml-0.5" />
                              </button>
                            </div>
                            <div className="absolute top-2 right-2 flex gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                              <button
                                onClick={() => handleDelete(item.id)}
                                disabled={deleting === item.id}
                                className="p-1.5 rounded-lg bg-red-500/30 text-red-400 hover:bg-red-500/50 transition-all"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </button>
                            </div>
                            <div className="p-2.5 bg-[#0F1017]">
                              <p className="text-xs font-semibold text-foreground truncate">{item.caption || item.fileName || "Survey Video"}</p>
                              <div className="flex items-center justify-between mt-0.5">
                                <p className="text-[11px] text-muted-foreground">{item.surveyorName}</p>
                                <p className="text-[10px] text-muted-foreground font-mono">{formatSize(item.fileSize)}</p>
                              </div>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}
                {filtered.some((m) => m.type === "document") && (
                  <div>
                    <h3 className="text-xs font-bold uppercase tracking-widest text-gold flex items-center gap-2 mb-3">
                      <FileText className="h-4 w-4" /> Documents & PDFs ({countByType("document")})
                    </h3>
                    <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                      {filtered.filter((m) => m.type === "document").map((item) => {
                        const lbIdx = lightboxItems.findIndex((i) => i.id === item.id);
                        const isPdf = item.fileName?.toLowerCase().endsWith(".pdf") || item.fileUrl?.toLowerCase().includes(".pdf");
                        return (
                          <div key={item.id} className="group relative rounded-xl border border-border/40 bg-card/30 p-3.5 hover:border-gold/40 transition-all flex items-start gap-3">
                            <div className={`p-3 rounded-xl shrink-0 ${isPdf ? "bg-red-500/10 text-red-400 border border-red-500/20" : "bg-blue-500/10 text-blue-400 border border-blue-500/20"}`}>
                              <FileText className="h-6 w-6" />
                            </div>
                            <div className="min-w-0 flex-1">
                              <p className="text-xs font-semibold text-foreground truncate">{item.caption || item.fileName || "Document File"}</p>
                              <p className="text-[11px] text-muted-foreground truncate">{item.fileName || "Document"}</p>
                              <div className="flex items-center gap-2 mt-2">
                                <span className="text-[10px] font-mono text-muted-foreground">{formatSize(item.fileSize)}</span>
                                <span className="text-[10px] text-muted-foreground">·</span>
                                <span className="text-[10px] text-gold truncate">{item.surveyorName || "Uploaded Doc"}</span>
                              </div>
                            </div>
                            <div className="flex items-center gap-1 shrink-0">
                              {lbIdx !== -1 && (
                                <button
                                  onClick={() => setLightboxIdx(lbIdx)}
                                  className="p-1.5 rounded-lg border border-border/60 text-muted-foreground hover:text-gold hover:border-gold/40 transition-all"
                                  title="View Details"
                                >
                                  <ZoomIn className="h-3.5 w-3.5" />
                                </button>
                              )}
                              {item.fileUrl && (
                                <a
                                  href={item.fileUrl}
                                  target="_blank"
                                  rel="noopener noreferrer"
                                  className="p-1.5 rounded-lg border border-border/60 text-muted-foreground hover:text-gold hover:border-gold/40 transition-all"
                                  title="Open File in New Tab"
                                >
                                  <ExternalLink className="h-3.5 w-3.5" />
                                </a>
                              )}
                              {item.fileUrl && (
                                <a
                                  href={item.fileUrl}
                                  download={item.fileName || "document"}
                                  className="p-1.5 rounded-lg border border-border/60 text-muted-foreground hover:text-gold hover:border-gold/40 transition-all"
                                  title="Download File"
                                >
                                  <Download className="h-3.5 w-3.5" />
                                </a>
                              )}
                              <button
                                onClick={() => handleDelete(item.id)}
                                disabled={deleting === item.id}
                                className="p-1.5 rounded-lg text-muted-foreground hover:text-destructive transition-all opacity-0 group-hover:opacity-100"
                                title="Delete Document"
                              >
                                <Trash2 className="h-3.5 w-3.5" />
                              </button>
                            </div>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}

                {filtered.some((m) => m.type === "note") && (
                  <div>
                    <h3 className="text-xs font-bold uppercase tracking-widest text-gold flex items-center gap-2 mb-3">
                      <StickyNote className="h-4 w-4" /> Surveyor Notes ({countByType("note")})
                    </h3>
                    <div className="space-y-3">
                      {filtered.filter((m) => m.type === "note").map((item) => (
                        <div key={item.id} className="group relative rounded-xl border border-border/40 bg-card/30 p-4">
                          <div className="flex items-start justify-between gap-3">
                            <div className="flex-1 min-w-0">
                              <p className="text-sm text-foreground leading-relaxed whitespace-pre-wrap">
                                {item.notes}
                              </p>
                              <div className="flex items-center gap-3 mt-2 text-[11px] text-muted-foreground">
                                <span className="flex items-center gap-1">
                                  <User className="h-3 w-3 text-gold" />
                                  {item.surveyorName || "Surveyor"}
                                </span>
                                <span>{item.createdAt ? new Date(item.createdAt).toLocaleString() : ""}</span>
                              </div>
                            </div>
                            <button
                              onClick={() => handleDelete(item.id)}
                              disabled={deleting === item.id}
                              className="p-1.5 rounded-lg text-muted-foreground hover:text-destructive transition-all opacity-0 group-hover:opacity-100"
                            >
                              <Trash2 className="h-4 w-4" />
                            </button>
                          </div>
                        </div>
                      ))}
                    </div>
                  </div>
                )}
              </div>
            )}
          </div>

          {survey.surveyorReportNotes && (
            <div className="px-5 pb-5 border-t border-border/30 pt-4">
              <SurveyReportView
                reportNotes={survey.surveyorReportNotes}
                propertyType={survey.moveType}
              />
            </div>
          )}
        </div>
      </div>

      {lightboxIdx !== null && (
        <MediaLightbox
          items={lightboxItems}
          startIndex={lightboxIdx}
          onClose={() => setLightboxIdx(null)}
        />
      )}
    </>
  );
}
