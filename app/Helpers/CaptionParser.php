<?php

namespace App\Helpers;

use App\Models\User;
use App\Models\Tag;
use App\Models\PostMention;

class CaptionParser
{
    public static function extractTags($caption)
    {
        preg_match_all('/#(\w+)/', $caption, $matches);
        return array_unique($matches[1]);
    }

    public static function extractMentions($caption)
    {
        preg_match_all('/@(\w+)/', $caption, $matches);
        return array_unique($matches[1]);
    }

    public static function handleTags($post, $tags)
    {
        foreach ($tags as $tagName) {
            $tag = Tag::firstOrCreate(['name' => $tagName]);
            $post->tags()->syncWithoutDetaching([$tag->tag_id]);
        }
    }

    public static function handleMentions($post, $mentions)
    {
        foreach ($mentions as $username) {
            $user = User::where('username', $username)->first();
            if ($user && $user->user_id !== $post->user_id) {
                PostMention::create([
                    'post_id' => $post->post_id,
                    'mentioned_user_id' => $user->user_id,
                ]);
            }
        }
    }
}
