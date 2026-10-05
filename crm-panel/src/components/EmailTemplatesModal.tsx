import { useState, useEffect } from "react";
import { Dialog, DialogContent, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Button } from "@/components/ui/button";
import { Mail, Send, Eye, Loader2, Sparkles, CheckCircle2 } from "lucide-react";
import { fetchEmailTemplates, previewLeadTemplate, sendLeadTemplateEmail } from "@/lib/api";

interface EmailTemplatesModalProps {
  open: boolean;
  onOpenChange: (open: boolean) => void;
  leadId: string;
  leadName: string;
  leadEmail: string;
}

export function EmailTemplatesModal({
  open,
  onOpenChange,
  leadId,
  leadName,
  leadEmail,
}: EmailTemplatesModalProps) {
  const [categories, setCategories] = useState<any[]>([]);
  const [loading, setLoading] = useState(false);
  const [selectedKey, setSelectedKey] = useState<string>("cat01_qualification.01_welcome");
  const [previewHtml, setPreviewHtml] = useState<string>("");
  const [previewLoading, setPreviewLoading] = useState(false);
  const [sending, setSending] = useState(false);
  const [sentSuccess, setSentSuccess] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    if (open) {
      setLoading(true);
      fetchEmailTemplates()
        .then((res) => {
          setCategories(res.categories || []);
          if (res.categories?.[0]?.templates?.[0]?.key) {
            setSelectedKey(res.categories[0].templates[0].key);
          }
        })
        .catch((err) => setError(err.message))
        .finally(() => setLoading(false));
    }
  }, [open]);

  useEffect(() => {
    if (open && leadId && selectedKey) {
      setPreviewLoading(true);
      setSentSuccess(false);
      setError("");
      previewLeadTemplate(leadId, selectedKey)
        .then((res) => setPreviewHtml(res.html || ""))
        .catch((err) => setError("Failed to render preview: " + err.message))
        .finally(() => setPreviewLoading(false));
    }
  }, [open, leadId, selectedKey]);

  const handleSend = async () => {
    setSending(true);
    setError("");
    setSentSuccess(false);
    try {
      await sendLeadTemplateEmail(leadId, selectedKey);
      setSentSuccess(true);
    } catch (err: any) {
      setError(err.message || "Failed to send email");
    } finally {
      setSending(false);
    }
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="max-w-5xl h-[85vh] flex flex-col p-0 overflow-hidden bg-[#0D0E12] border-gold/30">
        <DialogHeader className="p-6 border-b border-gold/15 bg-[#121319]">
          <div className="flex items-center justify-between">
            <div className="flex items-center gap-3">
              <div className="p-2.5 rounded-xl border border-gold/30 bg-gold/10 text-gold">
                <Sparkles className="h-5 w-5" />
              </div>
              <div>
                <DialogTitle className="text-xl font-semibold gold-text">
                  Next Gen Premium Email Library
                </DialogTitle>
                <p className="text-xs text-muted-foreground mt-0.5">
                  Client: <span className="text-foreground font-medium">{leadName}</span> ({leadEmail}) &bull; Lead Ref: <span className="text-gold font-mono">{leadId}</span>
                </p>
              </div>
            </div>
            {sentSuccess && (
              <div className="flex items-center gap-2 px-3 py-1.5 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-medium">
                <CheckCircle2 className="h-4 w-4" /> Email sent to client!
              </div>
            )}
          </div>
        </DialogHeader>

        <div className="grid grid-cols-12 flex-1 overflow-hidden">
          {/* Sidebar Category & Template Picker */}
          <div className="col-span-4 border-r border-gold/15 p-4 overflow-y-auto space-y-4 bg-[#0F1015]">
            {loading ? (
              <div className="flex items-center justify-center py-12 text-muted-foreground text-sm">
                <Loader2 className="h-4 w-4 animate-spin mr-2" /> Loading templates…
              </div>
            ) : (
              categories.map((cat) => (
                <div key={cat.id} className="space-y-2">
                  <div className="text-[11px] font-bold tracking-wider text-gold/80 uppercase px-2">
                    {cat.name}
                  </div>
                  <div className="space-y-1">
                    {cat.templates.map((tpl: any) => (
                      <button
                        key={tpl.key}
                        onClick={() => setSelectedKey(tpl.key)}
                        className={`w-full text-left p-3 rounded-xl border transition-all ${
                          selectedKey === tpl.key
                            ? "bg-gold/15 border-gold text-foreground shadow-[var(--shadow-gold)]"
                            : "bg-background/40 border-border/40 hover:border-gold/30 hover:bg-gold/5 text-muted-foreground"
                        }`}
                      >
                        <div className="text-xs font-semibold text-foreground flex items-center justify-between">
                          <span>{tpl.title}</span>
                          {selectedKey === tpl.key && <Eye className="h-3.5 w-3.5 text-gold shrink-0" />}
                        </div>
                        <div className="text-[11px] text-muted-foreground line-clamp-1 mt-0.5">
                          {tpl.subtitle}
                        </div>
                      </button>
                    ))}
                  </div>
                </div>
              ))
            )}
          </div>

          {/* HTML Preview Area */}
          <div className="col-span-8 flex flex-col bg-[#0B0C10] overflow-hidden">
            <div className="p-3 border-b border-gold/15 bg-[#121319] flex items-center justify-between text-xs text-muted-foreground">
              <div className="flex items-center gap-2">
                <Mail className="h-4 w-4 text-gold" />
                <span>Live Email HTML Rendering</span>
              </div>
              <Button
                onClick={handleSend}
                disabled={sending || previewLoading}
                className="bg-gradient-to-r from-gold-bright to-gold-dim text-primary-foreground font-semibold hover:shadow-[var(--shadow-gold)]"
                size="sm"
              >
                {sending ? (
                  <>
                    <Loader2 className="h-3.5 w-3.5 animate-spin mr-1.5" /> Sending…
                  </>
                ) : (
                  <>
                    <Send className="h-3.5 w-3.5 mr-1.5" /> Send Template to Client
                  </>
                )}
              </Button>
            </div>

            {error && (
              <div className="p-3 bg-destructive/10 border-b border-destructive/30 text-destructive text-xs">
                {error}
              </div>
            )}

            <div className="flex-1 p-4 overflow-y-auto flex justify-center">
              {previewLoading ? (
                <div className="flex items-center justify-center py-24 text-muted-foreground text-sm">
                  <Loader2 className="h-5 w-5 animate-spin mr-2 text-gold" /> Rendering luxury template…
                </div>
              ) : (
                <iframe
                  srcDoc={previewHtml}
                  title="Email Preview"
                  className="w-full h-full min-h-[500px] border border-gold/20 rounded-xl bg-white shadow-2xl"
                />
              )}
            </div>
          </div>
        </div>
      </DialogContent>
    </Dialog>
  );
}
