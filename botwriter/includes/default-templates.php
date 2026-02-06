<?php
/**
 * Default Templates for BotWriter
 * 
 * This file contains the default templates that are inserted into the database
 * when the plugin is activated. These templates provide various content styles
 * optimized for different purposes.
 * 
 * @package BotWriter
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Get all default templates to be inserted on plugin activation
 * 
 * @return array Array of template data
 */
function botwriter_get_default_templates() {
    return array(
        // Template 1: Default (Basic)
        array(
            'name' => 'Default Template',
            'is_default' => 1,
            'content' => 'Write an article for a blog, follow these instructions:

-The article must be HTML, with proper opening and closing H2-H4 tags for headings, and <p> for paragraphs.
-The length should be approximately {{post_length}} words.
-The article language must be: {{post_language}}.
-Narrative style: {{writer_style}}
-The topic must be related to some of the following keywords: {{prompt_or_keywords}}

-IMPORTANT: Do not title or label the last paragraph with Conclusion, Final Thoughts, Summary, or any similar term. The last paragraph should integrate naturally into the article, without any heading or subheading. It should subtly close the article by reinforcing the main message or idea, offering a final reflection, or leaving the reader with a powerful takeaway, but without explicitly indicating it is the end.',
        ),

        // Template 2: SEO Optimized Article
        array(
            'name' => 'SEO Optimized Article',
            'is_default' => 0,
            'content' => 'Write an SEO-optimized blog article following these guidelines:

-Format: HTML with H2-H4 headings and <p> paragraphs. Do NOT use H1.
-Length: Approximately {{post_length}} words.
-Language: {{post_language}}
-Writing style: {{writer_style}}
-Target keywords: {{prompt_or_keywords}}

SEO Requirements:
-Include the primary keyword naturally in the first 100 words.
-Use semantic variations and related terms throughout the content.
-Structure with clear H2 subheadings that include relevant keywords.
-Write short paragraphs (2-4 sentences) for better readability.
-Include a compelling hook in the introduction to reduce bounce rate.
-Add transitional phrases between sections for better flow.

-IMPORTANT: End the article naturally without using words like "Conclusion", "Summary", "Final Thoughts", or "In conclusion". The last paragraph should reinforce the main message while leaving the reader with actionable value.',
        ),

        // Template 3: Listicle / List Article
        array(
            'name' => 'Listicle (List Article)',
            'is_default' => 0,
            'content' => 'Write a listicle-style blog article with the following specifications:

-Format: HTML with numbered H2 headings for each list item, and <p> paragraphs for descriptions.
-Length: Approximately {{post_length}} words.
-Language: {{post_language}}
-Writing style: {{writer_style}}
-Topic keywords: {{prompt_or_keywords}}

Structure:
-Start with a brief introduction (2-3 paragraphs) explaining what the reader will learn.
-Each list item should have: a clear H2 heading with number, detailed explanation (2-4 paragraphs), and practical tips or examples.
-Use bullet points or sub-lists within items when appropriate.
-Make each item scannable and valuable on its own.

-IMPORTANT: Do not end with a "Conclusion" section. Instead, finish with the last list item and optionally add a brief closing thought that feels natural, not like a summary.',
        ),

        // Template 4: How-To Guide
        array(
            'name' => 'How-To Guide',
            'is_default' => 0,
            'content' => 'Write a comprehensive how-to guide following these instructions:

-Format: HTML with H2-H3 headings for steps and sections, <p> paragraphs, and bullet/numbered lists where helpful.
-Length: Approximately {{post_length}} words.
-Language: {{post_language}}
-Writing style: {{writer_style}}
-Topic: {{prompt_or_keywords}}

Structure:
-Introduction: Explain what the reader will accomplish and why it matters.
-Prerequisites or requirements section (if applicable).
-Step-by-step instructions with clear H2 headings for each major step.
-Include practical tips, warnings, or pro-tips in each step.
-Add examples or use cases to illustrate key points.

Writing Guidelines:
-Use action verbs at the beginning of each step.
-Be specific and avoid vague instructions.
-Anticipate common mistakes and address them.
-Write for beginners unless specified otherwise.

-IMPORTANT: End with the final step or a brief "next steps" suggestion. Avoid formal conclusion sections with labels like "Conclusion" or "Summary".',
        ),

        // Template 5: Product Review
        array(
            'name' => 'Product Review',
            'is_default' => 0,
            'content' => 'Write a detailed and balanced product review article:

-Format: HTML with H2-H3 headings and <p> paragraphs.
-Length: Approximately {{post_length}} words.
-Language: {{post_language}}
-Writing style: {{writer_style}}
-Product/Topic: {{prompt_or_keywords}}

Review Structure:
-Introduction: Brief overview of what the product/service is and who it\'s for.
-Key Features section: List and explain the main features with H3 subheadings.
-Pros and Cons: Honest assessment of advantages and disadvantages.
-User Experience: Describe how it feels to use the product.
-Comparison: Brief comparison with alternatives (if relevant).
-Who Should Buy This: Target audience recommendations.
-Value for Money: Assessment of pricing vs. features.

Guidelines:
-Be objective and balanced - mention both positives and negatives.
-Use specific examples and details, not generic statements.
-Include practical scenarios where the product excels or falls short.

-IMPORTANT: End with a clear recommendation without using "Conclusion" or "Final Verdict" as a heading. Just state your honest opinion naturally.',
        ),

        // Template 6: Comparison Article
        array(
            'name' => 'Comparison Article',
            'is_default' => 0,
            'content' => 'Write a comprehensive comparison article:

-Format: HTML with H2-H3 headings and <p> paragraphs.
-Length: Approximately {{post_length}} words.
-Language: {{post_language}}
-Writing style: {{writer_style}}
-Comparison topic: {{prompt_or_keywords}}

Article Structure:
-Introduction: Briefly introduce what\'s being compared and why the comparison matters.
-Quick Overview: Brief summary of each option.
-Detailed Comparison Sections (use H2 for each criterion):
  * Feature comparison
  * Pricing comparison
  * Ease of use
  * Performance
  * Best use cases for each
-Comparison summary (can be a simple list or brief paragraphs).

Guidelines:
-Be fair and objective to all options.
-Use specific data and examples when possible.
-Help readers understand which option fits their specific needs.
-Avoid declaring an absolute winner - focus on "best for X scenario".

-IMPORTANT: End by helping the reader make a decision based on their needs, without a formal "Conclusion" heading.',
        ),

        // Template 7: News/Trends Article
        array(
            'name' => 'News & Trends Article',
            'is_default' => 0,
            'content' => 'Write a news or trends article with journalistic quality:

-Format: HTML with H2 headings and <p> paragraphs.
-Length: Approximately {{post_length}} words.
-Language: {{post_language}}
-Writing style: {{writer_style}}
-Topic: {{prompt_or_keywords}}

Article Structure:
-Lead paragraph: Answer Who, What, When, Where, Why in the first paragraph.
-Context section: Background information the reader needs to understand the news.
-Main body: Detailed coverage of the topic with multiple angles.
-Expert perspectives or data points to support the story.
-Implications: What this means for readers or the industry.

Guidelines:
-Use inverted pyramid structure (most important info first).
-Keep paragraphs short (1-3 sentences for news style).
-Cite sources or data when making claims.
-Maintain objectivity - present facts, not opinions.
-Use active voice and strong verbs.

-IMPORTANT: End with forward-looking implications or what to watch next. No formal conclusion section needed.',
        ),

        // Template 8: Beginner's Guide
        array(
            'name' => 'Beginner\'s Guide',
            'is_default' => 0,
            'content' => 'Write a comprehensive beginner\'s guide:

-Format: HTML with H2-H3 headings, <p> paragraphs, and lists where helpful.
-Length: Approximately {{post_length}} words.
-Language: {{post_language}}
-Writing style: {{writer_style}}
-Topic: {{prompt_or_keywords}}

Structure for Beginners:
-Introduction: What this guide covers and what they\'ll learn.
-"What is [Topic]?" section: Clear definition and basic explanation.
-"Why does it matter?" section: Benefits and importance.
-Core concepts: Break down fundamental ideas (one H2 per concept).
-Getting started: First steps for beginners.
-Common mistakes to avoid.
-Resources or next steps for further learning.

Writing Guidelines:
-Avoid jargon or explain technical terms when first used.
-Use analogies and real-world examples to explain concepts.
-Assume zero prior knowledge.
-Build concepts progressively from simple to complex.
-Be encouraging and supportive in tone.

-IMPORTANT: End with encouragement and a simple first action the reader can take. Avoid formal conclusion headings.',
        ),

        // Template 9: Expert Deep Dive
        array(
            'name' => 'Expert Deep Dive',
            'is_default' => 0,
            'content' => 'Write an in-depth expert-level article:

-Format: HTML with H2-H4 headings for detailed sections, <p> paragraphs, and technical lists.
-Length: Approximately {{post_length}} words.
-Language: {{post_language}}
-Writing style: {{writer_style}}
-Topic: {{prompt_or_keywords}}

Expert Content Structure:
-Introduction: State the advanced topic and what makes this analysis unique.
-Background context: Brief recap for readers who need it.
-Core analysis sections (H2 for each major point):
  * Detailed technical explanations
  * Data, research, or case studies
  * Nuanced perspectives and edge cases
-Practical applications and advanced strategies.
-Future implications or emerging trends.

Guidelines:
-Write for an audience with existing knowledge of the subject.
-Use industry terminology appropriately.
-Support claims with data, research, or expert references.
-Explore nuances and edge cases.
-Provide original insights, not just rehashed information.
-Include actionable advanced tips.

-IMPORTANT: End with thought-provoking insights or predictions. Skip generic conclusion headings.',
        ),

        // Template 10: Storytelling/Narrative
        array(
            'name' => 'Storytelling Article',
            'is_default' => 0,
            'content' => 'Write an engaging narrative-style article:

-Format: HTML with H2 headings for sections and <p> paragraphs.
-Length: Approximately {{post_length}} words.
-Language: {{post_language}}
-Writing style: {{writer_style}}
-Topic: {{prompt_or_keywords}}

Narrative Structure:
-Hook: Start with a compelling story, question, or surprising fact.
-Setup: Introduce the context and characters/subjects involved.
-Rising action: Build tension or curiosity through the narrative.
-Key insights: Weave educational content into the story naturally.
-Resolution: Bring the narrative to a satisfying point.
-Takeaway: What the reader should remember or do.

Storytelling Guidelines:
-Use vivid descriptions and sensory details.
-Include dialogue or quotes when appropriate.
-Create emotional connection with the reader.
-Balance story with valuable information.
-Use scene-setting to transport the reader.
-Show, don\'t tell - use examples instead of abstract statements.

-IMPORTANT: End with the story\'s natural resolution or a powerful final image. No labeled conclusion needed.',
        ),

        // Template 11: Custom Prompt (Empty)
        array(
            'name' => 'Custom Prompt (Empty)',
            'is_default' => 0,
            'content' => '{{prompt_or_keywords}}',
        ),
    );
}

/**
 * Insert all default templates into the database
 * Called on plugin activation
 */
function botwriter_insert_all_default_templates() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_templates';
    
    // Check if any templates exist
    $existing_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    if ($existing_count > 0) {
        return; // Templates already exist, don't overwrite
    }
    
    $templates = botwriter_get_default_templates();
    
    foreach ($templates as $template) {
        $wpdb->insert(
            $table_name,
            array(
                'name' => $template['name'],
                'content' => $template['content'],
                'is_default' => $template['is_default'],
            )
        );
    }
}

/**
 * Reset templates to default (delete all and re-insert defaults)
 * Use with caution - this will delete user-created templates
 */
function botwriter_reset_templates_to_default() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'botwriter_templates';
    
    // Delete all existing templates
    $wpdb->query("TRUNCATE TABLE $table_name");
    
    // Insert defaults
    botwriter_insert_all_default_templates();
}

/**
 * Get a specific default template by name
 * Useful for restoring individual templates
 * 
 * @param string $name Template name
 * @return array|null Template data or null if not found
 */
function botwriter_get_default_template_by_name($name) {
    $templates = botwriter_get_default_templates();
    
    foreach ($templates as $template) {
        if ($template['name'] === $name) {
            return $template;
        }
    }
    
    return null;
}
