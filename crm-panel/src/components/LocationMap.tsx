import React, { useEffect, useRef } from "react";

interface LocationMapProps {
  latitude: number;
  longitude: number;
  label?: string;
  zoom?: number;
}

export function LocationMap({ latitude, longitude, label, zoom = 14 }: LocationMapProps) {
  const containerRef = useRef<HTMLDivElement>(null);
  const mapRef = useRef<any>(null);

  useEffect(() => {
    if (!containerRef.current) return;

    // Load OpenLayers map dynamically if not available
    const initMap = () => {
      if ((window as any).ol) {
        const ol = (window as any).ol;
        
        if (mapRef.current) {
          mapRef.current.setTarget(null);
        }

        const map = new ol.Map({
          target: containerRef.current,
          layers: [
            new ol.layer.Tile({
              source: new ol.source.OSM(),
            }),
          ],
          view: new ol.View({
            center: ol.proj.fromLonLat([longitude, latitude]),
            zoom,
          }),
        });

        // Add Marker
        const marker = new ol.Feature({
          geometry: new ol.geom.Point(ol.proj.fromLonLat([longitude, latitude])),
        });

        marker.setStyle(
          new ol.style.Style({
            image: new ol.style.Circle({
              radius: 8,
              fill: new ol.style.Fill({ color: "#d4af37" }),
              stroke: new ol.style.Stroke({ color: "#ffffff", width: 2 }),
            }),
          })
        );

        const vectorSource = new ol.source.Vector({ features: [marker] });
        const vectorLayer = new ol.layer.Vector({ source: vectorSource });
        map.addLayer(vectorLayer);

        mapRef.current = map;
      }
    };

    if (!(window as any).ol) {
      const link = document.createElement("link");
      link.rel = "stylesheet";
      link.href = "https://cdn.jsdelivr.net/npm/ol@v7.3.0/ol.css";
      document.head.appendChild(link);

      const script = document.createElement("script");
      script.src = "https://cdn.jsdelivr.net/npm/ol@v7.3.0/dist/ol.js";
      script.onload = initMap;
      document.head.appendChild(script);
    } else {
      initMap();
    }

    return () => {
      if (mapRef.current) {
        mapRef.current.setTarget(null);
      }
    };
  }, [latitude, longitude, zoom]);

  return (
    <div className="space-y-2">
      {label && <div className="text-xs font-medium text-gold">{label}</div>}
      <div
        ref={containerRef}
        className="h-48 w-full rounded-xl border border-border/60 overflow-hidden shadow-inner bg-secondary/30"
      />
      <div className="flex items-center justify-between text-[11px] text-muted-foreground">
        <span>Lat: {latitude.toFixed(4)}, Lon: {longitude.toFixed(4)}</span>
        <a
          href={`https://www.google.com/maps/search/?api=1&query=${latitude},${longitude}`}
          target="_blank"
          rel="noopener noreferrer"
          className="text-gold hover:underline"
        >
          Open in Maps ↗
        </a>
      </div>
    </div>
  );
}
