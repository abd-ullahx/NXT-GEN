import { useState } from "react";
import { companyLogo } from "@/lib/mock-data";
import { cn } from "@/lib/utils";

export function Logo({
  className,
  showText = true,
  size = 40,
}: {
  className?: string;
  showText?: boolean;
  size?: number;
}) {
  const [imgError, setImgError] = useState(false);

  return (
    <div className={cn("flex items-center gap-3", className)}>
      <div
        className="relative shrink-0 overflow-hidden rounded-xl border border-gold/30 bg-background/60 gold-ring flex items-center justify-center font-display font-bold text-gold"
        style={{ width: size, height: size }}
      >
        {!imgError ? (
          <img
            src={companyLogo}
            alt="Next Gen Relocation LTD logo"
            className="h-full w-full object-cover"
            onError={() => setImgError(true)}
          />
        ) : (
          <span className="text-xs font-bold tracking-wider gold-text">NG</span>
        )}
      </div>
      {showText && (
        <div className="leading-tight">
          <div className="font-display text-lg font-semibold tracking-wide gold-text">NEXT GEN</div>
          <div className="text-[10px] uppercase tracking-[0.35em] text-muted-foreground">Relocation Ltd</div>
        </div>
      )}
    </div>
  );
}
