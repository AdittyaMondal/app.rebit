<?php

namespace Tests\Feature;

use App\Enums\User\UserStatus;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * ReportType has always had COMMENT and STORY, and validation accepted them --
 * but fetchReportableData() had no case for either, so every such report threw
 * "Reportable resource by given id not found". Store UGC policy expects
 * reporting of user-generated content generally, not only posts.
 */
class ContentReportingTest extends TestCase
{
    use RefreshDatabase;

    private function user(): User
    {
        return User::create([
            'username' => 'u' . Str::random(8),
            'email' => Str::random(8) . '@example.test',
            'password' => 'irrelevant',
            'tips' => [],
            'status' => UserStatus::ACTIVE,
            // CreateUserAction always sets this from config in production;
            // ReportService types its locale as a non-nullable string.
            'language' => 'en',
        ]);
    }

    public function test_every_reportable_type_has_reasons(): void
    {
        // A type the UI offers but has no reason file for produces an empty
        // sheet the user cannot submit.
        foreach (['post', 'comment', 'story', 'user', 'group'] as $type) {
            $this->actingAs($this->user())
                ->getJson("/api/feedback/report/reasons?type={$type}")
                ->assertOk()
                ->assertJsonPath('data.reasons.0.title', fn ($title) => ! empty($title));
        }
    }

    public function test_a_comment_can_be_reported(): void
    {
        $author = $this->user();
        $post = Post::create(['user_id' => $author->id, 'content' => 'a post']);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'content' => 'a comment',
        ]);

        $this->actingAs($this->user())
            ->postJson('/api/feedback/report/send', [
                'type' => 'comment',
                'reason_index' => 0,
                'reportable_id' => $comment->id,
            ])->assertOk();

        $this->assertDatabaseHas('reports', [
            'reportable_id' => $comment->id,
            'type' => 'comment',
        ]);
    }

    public function test_a_story_can_be_reported(): void
    {
        $author = $this->user();
        $story = Story::create([
            'user_id' => $author->id,
            'story_uuid' => (string) Str::uuid(),
        ]);

        // Story::active() requires a live frame -- an expired story is not
        // something a user can be looking at, so it should not be reportable.
        \App\Models\StoryFrame::create([
            'story_id' => $story->id,
            'meta' => [],
            'status' => \App\Enums\Story\StoryStatus::ACTIVE,
            'expires_at' => now()->addDay(),
        ]);

        $this->actingAs($this->user())
            ->postJson('/api/feedback/report/send', [
                'type' => 'story',
                'reason_index' => 0,
                'reportable_id' => $story->id,
            ])->assertOk();

        $this->assertDatabaseHas('reports', [
            'reportable_id' => $story->id,
            'type' => 'story',
        ]);
    }

    public function test_you_cannot_report_your_own_comment(): void
    {
        $author = $this->user();
        $post = Post::create(['user_id' => $author->id, 'content' => 'a post']);
        $comment = Comment::create([
            'post_id' => $post->id,
            'user_id' => $author->id,
            'content' => 'a comment',
        ]);

        $this->actingAs($author)
            ->postJson('/api/feedback/report/send', [
                'type' => 'comment',
                'reason_index' => 0,
                'reportable_id' => $comment->id,
            ])->assertJsonPath('status', 'error');
    }
}
