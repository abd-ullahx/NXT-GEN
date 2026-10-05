import { useState, useEffect } from "react";
import { toast } from "sonner";
import { useConfirm } from "@/contexts/ConfirmContext";
import {
  UploadCloud,
  X,
  Play,
  Film,
  Trash2,
  CheckCircle2,
  Loader2,
  FileVideo,
  Sparkles,
  Info,
  Download,
} from "lucide-react";
import { Button } from "@/components/ui/button";
import { fetchSurveyVideos, uploadSurveyVideo, deleteSurveyVideo } from "@/lib/api";

interface VideoUploadModalProps {
  open: boolean;
  onClose: () => void;
  leadId?: string;
  leadName?: string;
}

export function VideoUploadModal({
  open,
  onClose,
  leadId,
  leadName,
}: VideoUploadModalProps) {
  const confirm = useConfirm();
  const [videos, setVideos] = useState<any[]>([]);
  const [loading, setLoading] = useState(true);
  const [uploading, setUploading] = useState(false);
  const [selectedFile, setSelectedFile] = useState<File | null>(null);
  const [title, setTitle] = useState("");
  const [notes, setNotes] = useState("");
  const [activePlaybackUrl, setActivePlaybackUrl] = useState<string | null>(null);

  const loadVideos = () => {
    setLoading(true);
    fetchSurveyVideos(leadId)
      .then((data) => setVideos(data || []))
      .catch((err) => console.error("Error loading survey videos:", err))
      .finally(() => setLoading(false));
  };

  useEffect(() => {
    if (open) {
      loadVideos();
    }
  }, [open, leadId]);

  if (!open) return null;

  const handleFileChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    if (e.target.files && e.target.files[0]) {
      const file = e.target.files[0];
      setSelectedFile(file);
      if (!title) {
        setTitle(file.name.replace(/\.[^/.]+$/, ""));
      }
    }
  };

  const handleUploadSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!selectedFile || uploading) return;

    setUploading(true);
    try {
      const formData = new FormData();
      formData.append("video", selectedFile);
      if (title.trim()) formData.append("title", title.trim());
      if (leadId) formData.append("lead_id", leadId);
      if (notes.trim()) formData.append("notes", notes.trim());

      await uploadSurveyVideo(formData);
      setSelectedFile(null);
      setTitle("");
      setNotes("");
      loadVideos();
    } catch (err: any) {
      toast.error("Failed to upload video recording: " + (err.message || "Unknown error"));
    } finally {
      setUploading(false);
    }
  };

  const handleDelete = async (videoId: number) => {
    if (!await confirm("Are you sure you want to delete this video recording?")) return;
    try {
      await deleteSurveyVideo(videoId);
      setVideos((prev) => prev.filter((v) => v.id !== videoId));
      if (activePlaybackUrl && videos.find((v) => v.id === videoId)?.videoUrl === activePlaybackUrl) {
        setActivePlaybackUrl(null);
      }
    } catch (err: any) {
      toast.error("Failed to delete video: " + err.message);
    }
  };

  const formatFileSize = (bytes: number) => {
    if (!bytes) return "0 MB";
    const mb = bytes / (1024 * 1024);
    return `${mb.toFixed(1)} MB`;
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/90 backdrop-blur-2xl p-4 md:p-6 overflow-y-auto animate-in fade-in duration-200">
      <div className="glass-card w-full max-w-5xl rounded-3xl flex flex-col border border-gold/40 shadow-2xl overflow-hidden bg-[#0A0B0E] max-h-[92vh]">
        
        {/* Modal Header */}
        <div className="p-5 border-b border-gold/20 bg-[#0F1017] flex items-center justify-between shrink-0">
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-2xl bg-blue-500/20 border border-blue-500/30 text-blue-400">
              <Film className="h-5 w-5" />
            </div>
            <div>
              <h2 className="font-display text-lg font-semibold text-foreground">
                Property Survey Video Storage & Asset Manager
              </h2>
              <p className="text-xs text-muted-foreground">
                Upload & stream property walk-through recordings {leadId && `for Lead ${leadName || leadId}`}
              </p>
            </div>
          </div>

          <button
            onClick={onClose}
            className="p-2 rounded-xl border border-border/60 bg-card/60 text-muted-foreground hover:text-foreground hover:border-gold/40 transition-all"
          >
            <X className="h-5 w-5" />
          </button>
        </div>

        {/* Modal Body */}
        <div className="p-6 overflow-y-auto space-y-6 flex-1">
          
          {/* Active Video Player Preview (if video selected for playback) */}
          {activePlaybackUrl && (
            <div className="rounded-3xl border border-gold/40 bg-black overflow-hidden relative shadow-2xl p-2 space-y-2">
              <div className="flex items-center justify-between px-3 py-1 text-xs text-gold font-semibold">
                <span>📹 Streaming Recording Playback</span>
                <button
                  onClick={() => setActivePlaybackUrl(null)}
                  className="text-muted-foreground hover:text-foreground text-[11px]"
                >
                  Close Player
                </button>
              </div>
              <video
                src={activePlaybackUrl}
                controls
                autoPlay
                className="w-full max-h-[380px] rounded-2xl border border-border/40"
              />
            </div>
          )}

          {/* Upload Form Box */}
          <form onSubmit={handleUploadSubmit} className="glass-card rounded-2xl p-5 border-gold/20 bg-[#121319] space-y-4">
            <div className="text-xs font-bold uppercase tracking-wider text-gold flex items-center gap-1.5">
              <UploadCloud className="h-4 w-4" /> Upload New Video Survey Recording
            </div>

            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
              <div className="space-y-1">
                <label className="text-xs text-muted-foreground font-medium">Recording Title *</label>
                <input
                  required
                  value={title}
                  onChange={(e) => setTitle(e.target.value)}
                  placeholder="e.g. Master Bedroom & Living Room Walkthrough"
                  className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                />
              </div>

              <div className="space-y-1">
                <label className="text-xs text-muted-foreground font-medium">Select Video File (.mp4, .webm, .mov) *</label>
                <input
                  type="file"
                  accept="video/mp4,video/webm,video/quicktime,video/x-msvideo"
                  onChange={handleFileChange}
                  className="w-full text-xs text-muted-foreground file:mr-3 file:py-1.5 file:px-3 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-gold/20 file:text-gold hover:file:bg-gold/30 cursor-pointer"
                />
              </div>

              <div className="space-y-1 md:col-span-2">
                <label className="text-xs text-muted-foreground font-medium">Surveyor Notes (Optional)</label>
                <input
                  value={notes}
                  onChange={(e) => setNotes(e.target.value)}
                  placeholder="e.g. Video shows steep stairwells and narrow hallway entrance."
                  className="w-full rounded-xl border border-border bg-input/40 py-2 px-3 text-xs outline-none focus:border-gold/50 text-foreground"
                />
              </div>
            </div>

            <div className="flex justify-end pt-2">
              <Button
                type="submit"
                disabled={uploading || !selectedFile}
                className="bg-gradient-to-r from-gold-bright to-gold-dim text-primary-foreground font-semibold shadow-[var(--shadow-gold)] disabled:opacity-50 text-xs"
                size="sm"
              >
                {uploading ? (
                  <>
                    <Loader2 className="h-4 w-4 animate-spin mr-1.5" /> Uploading Video File…
                  </>
                ) : (
                  <>
                    <UploadCloud className="h-4 w-4 mr-1.5" /> Upload Video Recording
                  </>
                )}
              </Button>
            </div>
          </form>

          {/* Uploaded Videos List Section */}
          <div className="space-y-3">
            <div className="flex items-center justify-between">
              <h3 className="text-sm font-semibold text-foreground flex items-center gap-2">
                <FileVideo className="h-4 w-4 text-gold" />
                Uploaded Survey Videos ({videos.length})
              </h3>
            </div>

            {loading ? (
              <div className="text-xs text-muted-foreground py-8 text-center flex items-center justify-center gap-2">
                <Loader2 className="h-4 w-4 animate-spin text-gold" /> Loading video assets…
              </div>
            ) : videos.length === 0 ? (
              <div className="p-8 rounded-2xl border border-dashed border-border/50 text-center text-xs text-muted-foreground space-y-2">
                <Film className="h-8 w-8 mx-auto text-muted-foreground/50" />
                <div>No video survey recordings uploaded yet.</div>
                <p className="text-[11px] text-muted-foreground">
                  Use the upload box above to add property inspection videos for estimators & clients.
                </p>
              </div>
            ) : (
              <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                {videos.map((vid) => (
                  <div
                    key={vid.id}
                    className="glass-card p-4 rounded-2xl border border-border/60 bg-[#121319] space-y-3 flex flex-col justify-between hover:border-gold/40 transition-all"
                  >
                    <div>
                      <div className="flex items-start justify-between gap-2">
                        <div>
                          <div className="font-semibold text-xs text-foreground line-clamp-1">
                            {vid.title}
                          </div>
                          <div className="text-[10px] text-muted-foreground font-mono mt-0.5">
                            {vid.fileName} · {formatFileSize(vid.fileSize)}
                          </div>
                        </div>

                        <button
                          onClick={() => handleDelete(vid.id)}
                          className="text-muted-foreground hover:text-destructive p-1"
                          title="Delete video"
                        >
                          <Trash2 className="h-4 w-4" />
                        </button>
                      </div>

                      {vid.notes && (
                        <p className="text-xs text-muted-foreground mt-2 italic bg-background/30 p-2 rounded-xl border border-border/40">
                          "{vid.notes}"
                        </p>
                      )}
                    </div>

                    <div className="flex items-center justify-between pt-3 border-t border-border/40 text-[11px]">
                      <span className="text-muted-foreground font-mono text-[10px]">
                        Uploaded: {new Date(vid.createdAt).toLocaleDateString("en-GB")}
                      </span>

                      <div className="flex items-center gap-2">
                        <a
                          href={vid.videoUrl}
                          target="_blank"
                          rel="noreferrer"
                          className="p-1.5 rounded-lg border border-border text-muted-foreground hover:text-foreground text-[10px]"
                          title="Download Video File"
                        >
                          <Download className="h-3.5 w-3.5" />
                        </a>
                        <Button
                          type="button"
                          onClick={() => setActivePlaybackUrl(vid.videoUrl)}
                          size="sm"
                          className="bg-gold/15 border border-gold/30 text-gold hover:bg-gold/30 text-[11px] font-semibold py-1 px-3"
                        >
                          <Play className="h-3 w-3 mr-1 fill-gold" /> Play Recording
                        </Button>
                      </div>
                    </div>
                  </div>
                ))}
              </div>
            )}
          </div>

        </div>

      </div>
    </div>
  );
}
