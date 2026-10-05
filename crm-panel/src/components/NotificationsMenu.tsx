import { useState, useEffect, useRef } from "react";
import { Bell, Check, Trash } from "lucide-react";
import { Link } from "@tanstack/react-router";
import { toast } from "sonner";
import { fetchAppNotifications, markNotificationAsRead, markAllNotificationsAsRead } from "@/lib/api";
import { cn } from "@/lib/utils";
import {
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";

interface AppNotification {
  id: number;
  title: string;
  message: string;
  type: string;
  link: string | null;
  is_read: boolean;
  created_at: string;
}

export function NotificationsMenu() {
  const [notifications, setNotifications] = useState<AppNotification[]>([]);
  const [unreadCount, setUnreadCount] = useState(0);
  const [open, setOpen] = useState(false);
  const latestNotificationIdRef = useRef<number>(0);

  const fetchNotifications = async () => {
    try {
      const data = await fetchAppNotifications();
      const fetchedNotifications = data.notifications;
      const fetchedUnreadCount = data.unreadCount;
      
      setNotifications(fetchedNotifications);
      setUnreadCount(fetchedUnreadCount);

      // Check for new notifications to show toast
      if (fetchedNotifications.length > 0) {
        const newestId = fetchedNotifications[0].id;
        if (latestNotificationIdRef.current > 0 && newestId > latestNotificationIdRef.current) {
          // Find all new notifications since last check
          const newNotifs = fetchedNotifications.filter((n: AppNotification) => n.id > latestNotificationIdRef.current);
          newNotifs.forEach((notif: AppNotification) => {
            toast(notif.title, {
              description: notif.message,
              action: notif.link ? {
                label: "View",
                onClick: () => window.location.href = notif.link!
              } : undefined,
            });
          });
        }
        latestNotificationIdRef.current = newestId;
      }
    } catch (error) {
      console.error("Failed to fetch notifications", error);
    }
  };

  useEffect(() => {
    fetchNotifications();
    const interval = setInterval(fetchNotifications, 12000); // poll every 12 seconds
    return () => clearInterval(interval);
  }, []);

  const markAsRead = async (id: number) => {
    try {
      await markNotificationAsRead(id);
      setNotifications(prev => prev.filter(n => n.id !== id));
      setUnreadCount(prev => Math.max(0, prev - 1));
    } catch (error) {
      console.error("Failed to mark as read", error);
    }
  };

  const markAllAsRead = async () => {
    try {
      await markAllNotificationsAsRead();
      setNotifications([]);
      setUnreadCount(0);
    } catch (error) {
      console.error("Failed to mark all as read", error);
    }
  };

  return (
    <Popover open={open} onOpenChange={setOpen}>
      <PopoverTrigger asChild>
        <button className="relative rounded-full border border-border bg-card/60 p-2 transition-colors hover:border-gold/40">
          <Bell className="h-[18px] w-[18px] text-muted-foreground" />
          {unreadCount > 0 && (
            <span className="absolute -right-0.5 -top-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-gold text-[9px] font-bold text-primary-foreground">
              {unreadCount > 99 ? '99+' : unreadCount}
            </span>
          )}
        </button>
      </PopoverTrigger>
      <PopoverContent className="w-80 p-0" align="end" sideOffset={8}>
        <div className="flex items-center justify-between border-b border-border/50 px-4 py-3">
          <h4 className="font-semibold text-foreground">Notifications</h4>
          {unreadCount > 0 && (
            <button
              onClick={markAllAsRead}
              className="text-xs text-muted-foreground hover:text-gold transition-colors"
            >
              Mark all as read
            </button>
          )}
        </div>
        <div className="max-h-[400px] overflow-y-auto py-1">
          {notifications.length === 0 ? (
            <div className="px-4 py-8 text-center text-sm text-muted-foreground">
              No notifications yet.
            </div>
          ) : (
            notifications.map((notif) => (
              <div
                key={notif.id}
                onClick={() => {
                  if (!notif.is_read) markAsRead(notif.id);
                  if (notif.link) window.location.href = notif.link;
                }}
                className={cn(
                  "cursor-pointer px-4 py-3 hover:bg-muted/50 transition-colors border-b border-border/20 last:border-0",
                  "bg-muted/20"
                )}
              >
                <div className="flex items-start justify-between gap-2">
                  <div>
                    <p className="text-sm font-medium text-foreground">
                      {notif.title}
                    </p>
                    <p className="mt-1 text-xs text-muted-foreground line-clamp-2">
                      {notif.message}
                    </p>
                    <p className="mt-1.5 text-[10px] text-muted-foreground/70">
                      {new Date(notif.created_at).toLocaleString()}
                    </p>
                  </div>
                  <div className="h-2 w-2 mt-1 shrink-0 rounded-full bg-gold" />
                </div>
              </div>
            ))
          )}
        </div>
      </PopoverContent>
    </Popover>
  );
}
