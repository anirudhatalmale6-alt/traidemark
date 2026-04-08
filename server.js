import express from "express";
import { createServer as createViteServer } from "vite";
import fetch from "node-fetch";
import dotenv from "dotenv";
import path from "path";
import { fileURLToPath } from "url";

dotenv.config();

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PORT = process.env.PORT || 3000;
const isDev = process.env.NODE_ENV !== "production";

const app = express();
app.use(express.json({ limit: "10mb" }));

// Claude API proxy
app.post("/api/claude", async (req, res) => {
  const { systemPrompt, userContent } = req.body;
  if (!systemPrompt || !userContent) {
    return res.status(400).json({ error: "Missing systemPrompt or userContent" });
  }
  try {
    const apiRes = await fetch("https://api.anthropic.com/v1/messages", {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "x-api-key": process.env.ANTHROPIC_API_KEY,
        "anthropic-version": "2023-06-01",
      },
      body: JSON.stringify({
        model: "claude-sonnet-4-20250514",
        max_tokens: 3000,
        system: systemPrompt,
        messages: [{ role: "user", content: userContent }],
      }),
    });
    const data = await apiRes.json();
    if (data.error) {
      return res.status(apiRes.status).json({ error: data.error.message || "Claude API error" });
    }
    const text = data.content?.map((b) => b.text || "").join("") || "";
    res.json({ text });
  } catch (err) {
    console.error("Claude API error:", err);
    res.status(500).json({ error: "Error connecting to Claude API" });
  }
});

if (isDev) {
  // Development: use Vite dev server as middleware
  const vite = await createViteServer({
    server: { middlewareMode: true },
    appType: "spa",
  });
  app.use(vite.middlewares);
} else {
  // Production: serve built files
  app.use(express.static(path.join(__dirname, "dist")));
  app.get("/{*splat}", (req, res) => {
    res.sendFile(path.join(__dirname, "dist", "index.html"));
  });
}

app.listen(PORT, () => {
  console.log(`Server running on http://localhost:${PORT}`);
});
