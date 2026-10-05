import { defineConfig } from "@lovable.dev/vite-tanstack-config";

export default defineConfig({
  tanstackStart: {
    server: { entry: "server" },
  },
  vite: {
    base: "/crm/",
    optimizeDeps: {
      include: [
        "react",
        "react-dom",
        "@tanstack/react-query",
        "@tanstack/react-router",
        "lucide-react",
        "date-fns",
        "recharts",
      ],
    },
    server: {
      port: 5174,
      host: true,
      allowedHosts: true,
      proxy: {
        "/api": {
          target: "http://127.0.0.1:8000",
          changeOrigin: true,
          secure: false,
        },
        "/quotation": {
          target: "http://127.0.0.1:8000",
          changeOrigin: true,
          secure: false,
        },
        "/book-survey": {
          target: "http://127.0.0.1:8000",
          changeOrigin: true,
          secure: false,
        },
        "/payment": {
          target: "http://127.0.0.1:8000",
          changeOrigin: true,
          secure: false,
        },
      },
    },
    build: {
      target: "esnext",
      cssCodeSplit: true,
      minify: "esbuild",
      rollupOptions: {
        output: {
          manualChunks(id) {
            if (id.includes("node_modules")) {
              if (id.includes("recharts") || id.includes("d3-")) {
                return "charts-vendor";
              }
              if (id.includes("lucide-react")) {
                return "icons-vendor";
              }
              if (id.includes("date-fns")) {
                return "date-vendor";
              }
              if (id.includes("@tanstack")) {
                return "tanstack-vendor";
              }
            }
          },
        },
      },
    },
  },
});
