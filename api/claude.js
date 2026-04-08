export default async function handler(req, res) {
  if (req.method !== "POST") {
    return res.status(405).json({ error: "Method not allowed" });
  }

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
}
