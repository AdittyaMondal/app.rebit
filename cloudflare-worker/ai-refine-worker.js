/**
 * Rebit AI Post Refinement Worker
 * 
 * Cloudflare Worker that acts as a secure middleware between the Rebit app
 * and Cloudflare Workers AI for post text refinement.
 * 
 * Features:
 * - Auto-detects the input language and responds in the SAME language
 * - Optional `target_language` parameter to force output in a specific language
 * - Text formatting support (bold, italic, underline, highlight, strikethrough)
 * 
 * Deployment:
 * 1. Create a new Cloudflare Worker via the Cloudflare dashboard
 * 2. Paste this script into the Worker editor
 * 3. Add the AI binding: Go to Settings > Variables > AI Bindings > Add binding > Variable name: AI
 * 4. Add the environment variable: AI_REFINE_SECRET_TOKEN = <your-secret-token>
 * 5. Deploy and copy the Worker URL to your .env file
 */

export default {
    async fetch(request, env) {
        // Handle CORS preflight
        if (request.method === 'OPTIONS') {
            return new Response(null, {
                status: 204,
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'Access-Control-Allow-Methods': 'POST, OPTIONS',
                    'Access-Control-Allow-Headers': 'Content-Type, Authorization',
                    'Access-Control-Max-Age': '86400',
                },
            });
        }

        // Only allow POST requests
        if (request.method !== 'POST') {
            return jsonResponse({ error: 'Method not allowed' }, 405);
        }

        // Validate authentication token
        const authHeader = request.headers.get('Authorization');
        const expectedToken = env.AI_REFINE_SECRET_TOKEN;

        if (!authHeader || authHeader !== `Bearer ${expectedToken}`) {
            return jsonResponse({ error: 'Unauthorized' }, 401);
        }

        try {
            const body = await request.json();
            const { text, target_language } = body;

            if (!text || typeof text !== 'string' || text.trim().length === 0) {
                return jsonResponse({ error: 'Text is required' }, 400);
            }

            if (text.length > 10000) {
                return jsonResponse({ error: 'Text is too long (max 10000 characters)' }, 400);
            }

            // Detect the language of the input text
            const detectedLang = target_language || detectLanguage(text);

            // Call Cloudflare Workers AI
            const aiResponse = await env.AI.run('@cf/meta/llama-3.1-8b-instruct', {
                messages: [
                    {
                        role: 'system',
                        content: `You are a social media post editor for Rebit. Refine and improve posts.

ABSOLUTE RULE: The input is written in ${detectedLang}. You MUST respond ONLY in ${detectedLang}. Do NOT translate. Do NOT use any other language. Every single word and hashtag in your response must be in ${detectedLang}.

Formatting syntax available:
**bold** | *italic* | ~~strikethrough~~ | ++underline++ | ::highlight::

Instructions:
- Improve clarity, tone, and flow while keeping the original meaning
- Use formatting sparingly for emphasis on key words
- Add 3-5 relevant hashtags at the end (in ${detectedLang})
- Return ONLY the refined post — no prefixes, no quotes, no notes, no explanations
- Keep it concise and punchy for social media
- Preserve any @mentions
- Use line breaks between ideas`
                    },
                    {
                        role: 'user',
                        content: `[Language: ${detectedLang}] Refine this post in ${detectedLang} only:\n\n${text}`
                    }
                ],
                max_tokens: 1024,
                temperature: 0.7,
            });

            const refinedText = aiResponse.response || aiResponse.result?.response;

            if (!refinedText) {
                return jsonResponse({ error: 'AI did not return a response' }, 500);
            }

            // Clean up the response — remove any "Note:" or "Translation:" lines the AI might add
            const cleanedText = refinedText
                .trim()
                .replace(/^\s*\(?Note:.*$/gmi, '')
                .replace(/^\s*\(?Translation:.*$/gmi, '')
                .replace(/^\s*\(?Nota:.*$/gmi, '')
                .replace(/^\s*\(?Traducción:.*$/gmi, '')
                .replace(/^\s*I (?:translated|refined|kept).*$/gmi, '')
                .replace(/\n{3,}/g, '\n\n')
                .trim();

            return jsonResponse({ refined_text: cleanedText }, 200);

        } catch (error) {
            console.error('AI Refine Error:', error);
            return jsonResponse({ error: 'Failed to process AI refinement' }, 500);
        }
    },
};

function jsonResponse(data, status = 200) {
    return new Response(JSON.stringify(data), {
        status,
        headers: {
            'Content-Type': 'application/json',
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'POST, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization',
        },
    });
}

/**
 * Detect the primary language of a text based on Unicode character ranges
 * and common word patterns. Returns a language name string.
 */
function detectLanguage(text) {
    // Count characters by script
    const scripts = {
        devanagari: 0,  // Hindi, Sanskrit, Marathi, Nepali
        arabic: 0,       // Arabic, Urdu, Persian
        cjk: 0,          // Chinese
        hiragana: 0,     // Japanese
        katakana: 0,     // Japanese
        hangul: 0,       // Korean
        cyrillic: 0,     // Russian, Ukrainian
        thai: 0,
        tamil: 0,
        telugu: 0,
        bengali: 0,
        gujarati: 0,
        latin: 0,
    };

    for (const char of text) {
        const code = char.codePointAt(0);
        if (code >= 0x0900 && code <= 0x097F) scripts.devanagari++;
        else if (code >= 0x0600 && code <= 0x06FF) scripts.arabic++;
        else if (code >= 0x4E00 && code <= 0x9FFF) scripts.cjk++;
        else if (code >= 0x3040 && code <= 0x309F) scripts.hiragana++;
        else if (code >= 0x30A0 && code <= 0x30FF) scripts.katakana++;
        else if (code >= 0xAC00 && code <= 0xD7AF) scripts.hangul++;
        else if (code >= 0x0400 && code <= 0x04FF) scripts.cyrillic++;
        else if (code >= 0x0E00 && code <= 0x0E7F) scripts.thai++;
        else if (code >= 0x0B80 && code <= 0x0BFF) scripts.tamil++;
        else if (code >= 0x0C00 && code <= 0x0C7F) scripts.telugu++;
        else if (code >= 0x0980 && code <= 0x09FF) scripts.bengali++;
        else if (code >= 0x0A80 && code <= 0x0AFF) scripts.gujarati++;
        else if ((code >= 0x0041 && code <= 0x024F) || (code >= 0x1E00 && code <= 0x1EFF)) scripts.latin++;
    }

    // Find dominant script
    const dominant = Object.entries(scripts).sort((a, b) => b[1] - a[1])[0];

    if (dominant[1] === 0) return 'English';

    const scriptMap = {
        devanagari: 'Hindi',
        arabic: 'Arabic',
        cjk: 'Chinese',
        hiragana: 'Japanese',
        katakana: 'Japanese',
        hangul: 'Korean',
        cyrillic: 'Russian',
        thai: 'Thai',
        tamil: 'Tamil',
        telugu: 'Telugu',
        bengali: 'Bengali',
        gujarati: 'Gujarati',
    };

    if (dominant[0] !== 'latin') {
        return scriptMap[dominant[0]] || 'English';
    }

    // For Latin script, detect common language patterns
    const lower = text.toLowerCase();
    const spanishWords = /\b(el|la|los|las|de|del|en|que|por|con|una|para|como|más|pero|este|esta|muy|también|sobre|entre|desde|donde|puede)\b/g;
    const frenchWords = /\b(le|la|les|des|est|que|dans|pour|avec|sur|une|pas|ont|fait|bien|mais|même|aussi|tout|cette|être|nous|vous|aux)\b/g;
    const portugueseWords = /\b(o|os|da|das|do|dos|em|que|por|com|uma|para|como|mais|mas|este|esta|muito|também|sobre|entre|desde|onde|pode|não)\b/g;
    const germanWords = /\b(der|die|das|und|ist|ein|eine|mit|auf|für|den|dem|nicht|von|auch|sich|des|oder|als|nach|bei|wird)\b/g;

    const spanishCount = (lower.match(spanishWords) || []).length;
    const frenchCount = (lower.match(frenchWords) || []).length;
    const portugueseCount = (lower.match(portugueseWords) || []).length;
    const germanCount = (lower.match(germanWords) || []).length;

    const langScores = { Spanish: spanishCount, French: frenchCount, Portuguese: portugueseCount, German: germanCount };
    const topLang = Object.entries(langScores).sort((a, b) => b[1] - a[1])[0];

    // Only classify as non-English if there are enough indicator words (at least 3)
    if (topLang[1] >= 3) {
        return topLang[0];
    }

    return 'English';
}
