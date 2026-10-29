<?php

declare(strict_types=1);

namespace Tests\Feature\Coaching;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\Coaching;
use Tests\TestCase;

final class TeacherTest extends TestCase
{
    use Coaching;
    use RefreshDatabase;

    public function test_a_plus_singer_sends_takes_to_their_own_teacher_with_no_money(): void
    {
        [$singer, $take] = $this->singerWithTake();
        $this->assertMatchesContract($this->postJson('/api/teacher-links', ['name' => 'Ms Okafor']), 'teacher.links.create', 403);
        $this->assertMatchesContract($this->postJson('/api/teacher-links', []), 'teacher.links.create', 422);

        $singer->forceFill(['plan' => 'plus'])->save();
        $this->as($singer);
        $this->assertMatchesContract($this->postJson('/api/teacher-links', ['name' => 'Ms Okafor']), 'teacher.links.create', 403);
        $made = $this->postJson('/api/teacher-links', ['name' => 'Ms Okafor', 'adult' => true]);
        $this->assertMatchesContract($made, 'teacher.links.create', 201);
        $token = Str::afterLast((string) $made->json('data.url'), '/teacher/');
        $this->assertMatchesContract($this->postJson('/api/teacher/'.$token, ['adult' => true]), 'teacher.links.accept', 409);

        $teacher = $this->as(User::factory()->create(['name' => 'Grace Okafor']));
        $shown = $this->getJson('/api/teacher/'.$token);
        $this->assertMatchesContract($shown, 'teacher.links.show', 200);
        $shown->assertJsonPath('data.singer.name', $singer->name);
        $this->assertMatchesContract($this->getJson('/api/teacher/nope'), 'teacher.links.show', 404);
        $this->assertMatchesContract($this->postJson('/api/teacher/'.$token), 'teacher.links.accept', 403);
        $accepted = $this->postJson('/api/teacher/'.$token, ['adult' => true]);
        $this->assertMatchesContract($accepted, 'teacher.links.accept', 200);
        $accepted->assertJsonPath('data.engagement.kind', 'teacher')->assertJsonPath('data.engagement.money', null);
        $this->assertMatchesContract($this->getJson('/api/teacher/'.$token), 'teacher.links.show', 410);
        $this->assertMatchesContract($this->postJson('/api/teacher/'.$token, ['adult' => true]), 'teacher.links.accept', 410);
        $this->assertMatchesContract($this->postJson('/api/teacher/nope'), 'teacher.links.accept', 404);
        $id = (string) $accepted->json('data.engagement.id');

        $this->as($singer);
        $sent = $this->postJson('/api/engagements/'.$id.'/reviews', ['takeId' => $take, 'question' => 'How was this?']);
        $sent->assertCreated();
        $this->as($teacher);
        $review = (string) $sent->json('data.id');
        $this->putJson('/api/reviews/'.$review, ['comments' => [['atSeconds' => 1, 'text' => 'Good.']], 'summary' => ['working' => 'Pitch.', 'toWorkOn' => ['Breath'], 'practise' => 'Scales.'], 'exercises' => []])->assertOk();
        $this->postJson('/api/reviews/'.$review.'/deliver')->assertOk();

        // Either side ends it.
        $this->postJson('/api/engagements/'.$id.'/cancel')->assertOk()->assertJsonPath('data.engagement.status', 'ended');
    }
}
