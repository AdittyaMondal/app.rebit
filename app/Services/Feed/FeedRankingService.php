<?php

namespace App\Services\Feed;

use App\Database\Configs\Table;
use Illuminate\Database\Eloquent\Builder;

class FeedRankingService
{
    /**
     * Apply smart feed ranking for the Home feed (personalized for logged-in user).
     *
     * Uses orderByRaw instead of selectRaw to avoid breaking simplePaginate.
     * 
     * Scoring considers:
     * - Engagement: comments, bookmarks, shares, views, quotes
     * - Recency: exponential decay (half-life ~12 hours)
     * - Follow bonus: +20 if viewer follows the author
     * - Velocity: engagement-per-hour for posts < 6 hours old
     * - Pinned posts: massive boost for global_pinned posts
     */
    public static function applyHomeFeedRanking(Builder $query, int $userId): Builder
    {
        $followsTable = Table::FOLLOWS;

        return $query->orderByRaw("
            (
                /* Engagement score */
                (
                    posts.comments_count * 5
                    + posts.bookmarks_count * 2
                    + posts.shares_count * 4
                    + posts.views_count * 0.1
                    + posts.quotes_count * 3
                )
                /* Recency multiplier: exponential decay, half-life ~12 hours */
                * EXP(-0.058 * GREATEST(TIMESTAMPDIFF(HOUR, posts.created_at, NOW()), 0))
                /* Follow bonus: +20 if the viewer follows the author */
                + (CASE WHEN EXISTS (
                    SELECT 1 FROM {$followsTable}
                    WHERE follower_id = {$userId} AND following_id = posts.user_id
                ) THEN 20 ELSE 0 END)
                /* Velocity bonus: engagement-per-hour for posts < 6 hours old */
                + (CASE WHEN TIMESTAMPDIFF(HOUR, posts.created_at, NOW()) < 6 AND TIMESTAMPDIFF(HOUR, posts.created_at, NOW()) > 0 THEN
                    (posts.comments_count * 5 + posts.bookmarks_count * 2 + posts.shares_count * 4 + posts.views_count * 0.1)
                    / GREATEST(TIMESTAMPDIFF(HOUR, posts.created_at, NOW()), 1)
                ELSE 0 END)
                /* Global pinned posts get a massive boost */
                + (CASE WHEN posts.global_pinned = 1 THEN 1000 ELSE 0 END)
            ) DESC, posts.id DESC
        ");
    }

    /**
     * Apply smart feed ranking for the Explore feed (global trending content).
     *
     * Similar to home feed but without follow bonus — focuses on pure engagement and trending velocity.
     * Higher engagement weights and steeper recency decay to surface trending content.
     */
    public static function applyExploreFeedRanking(Builder $query): Builder
    {
        return $query->orderByRaw("
            (
                /* Engagement score weighted more heavily for explore/trending */
                (
                    posts.comments_count * 6
                    + posts.bookmarks_count * 3
                    + posts.shares_count * 5
                    + posts.views_count * 0.2
                    + posts.quotes_count * 4
                )
                /* Recency: steeper decay for explore — half-life ~8 hours */
                * EXP(-0.087 * GREATEST(TIMESTAMPDIFF(HOUR, posts.created_at, NOW()), 0))
                /* Velocity bonus: higher weight for rapid engagement on fresh posts */
                + (CASE WHEN TIMESTAMPDIFF(HOUR, posts.created_at, NOW()) < 6 AND TIMESTAMPDIFF(HOUR, posts.created_at, NOW()) > 0 THEN
                    (posts.comments_count * 6 + posts.bookmarks_count * 3 + posts.shares_count * 5 + posts.views_count * 0.2)
                    / GREATEST(TIMESTAMPDIFF(HOUR, posts.created_at, NOW()), 1) * 2
                ELSE 0 END)
                /* Global pinned posts boost */
                + (CASE WHEN posts.global_pinned = 1 THEN 1000 ELSE 0 END)
            ) DESC, posts.id DESC
        ");
    }
}
