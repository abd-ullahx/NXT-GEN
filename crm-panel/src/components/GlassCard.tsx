import { cn } from "@/lib/utils";
import type { ReactNode } from "react";

export function GlassCard({
  children,
  className,
  hover = false,
}: {
  children: ReactNode;
  className?: string;
  hover?: boolean;
}) {
  return (
    <div
      className={cn(
        "glass-card rounded-2xl",
        hover && "transition-all duration-300 hover:-translate-y-0.5 hover:border-gold/40",
        className,
      )}
    >
      {children}
    </div>
  );
}
