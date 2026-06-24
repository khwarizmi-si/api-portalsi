<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class UserImportController extends Controller
{
    public function show(Request $request)
    {
        $this->guard($request);

        return view('import-users', [
            'summary' => session('summary'),
            'importErrors' => session('importErrors', []),
        ]);
    }

    public function store(Request $request)
    {
        $this->guard($request);

        $request->validate([
            'users_file' => 'required|file|max:51200',
            'default_role' => 'nullable|in:teacher,parent,student',
            'fresh_import' => 'nullable|boolean',
            'verify_email' => 'nullable|boolean',
        ]);

        $file = $request->file('users_file');
        abort_unless(
            in_array(strtolower($file->getClientOriginalExtension()), ['csv', 'txt', 'json', 'sql'], true),
            422,
            'File harus berekstensi csv, txt, json, atau sql.'
        );

        $rows = $this->parseFile($file->getRealPath());
        $defaultRole = $request->input('default_role', 'student');
        $freshImport = $request->boolean('fresh_import', true);
        $verifyEmail = $request->boolean('verify_email', true);
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'fresh' => $freshImport];
        $errors = [];

        if ($freshImport) {
            $this->freshUserData();
        }

        DB::transaction(function () use ($rows, $defaultRole, $verifyEmail, $freshImport, &$summary, &$errors) {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $normalized = $this->normalizeRow($row);

                if (empty($normalized['username']) || empty($normalized['password'])) {
                    $summary['skipped']++;
                    $errors[] = "Baris {$line}: username dan password wajib ada.";
                    continue;
                }

                try {
                    $user = $freshImport ? null : $this->findExistingUser($normalized);
                    $exists = $user !== null;
                    $user ??= new User();

                    if (!empty($normalized['id']) && !$exists) {
                        $user->user_id = (int) $normalized['id'];
                    }

                    $createdAt = $this->parseDate($normalized['created_at'] ?? null) ?? now();
                    $user->username = $this->uniqueUsername($normalized['username'], $user->user_id);
                    $user->full_name = $normalized['full_name'] ?: null;
                    $user->email = $normalized['email'] ?: null;
                    $user->password_hash = $this->toPasswordHash($normalized['password']);
                    $user->role = $normalized['role'] ?? $defaultRole;
                    $user->profile_picture_url = null;
                    $user->banner_url = null;
                    $user->created_at = $user->exists ? $user->created_at : $createdAt;
                    $user->updated_at = now();
                    $user->email_verified_at = $verifyEmail && $user->email
                        ? ($user->email_verified_at ?? $createdAt)
                        : $user->email_verified_at;
                    $user->save();

                    $summary[$exists ? 'updated' : 'created']++;
                } catch (\Throwable $e) {
                    $summary['skipped']++;
                    $errors[] = "Baris {$line}: " . $e->getMessage();
                }
            }
        });

        return redirect()
            ->route('users.import.show', ['token' => $request->input('token')])
            ->with('summary', $summary)
            ->with('importErrors', $errors);
    }

    private function guard(Request $request): void
    {
        if (app()->environment('local')) {
            return;
        }

        $token = (string) env('USER_IMPORT_TOKEN', '');
        abort_unless($token !== '' && hash_equals($token, (string) $request->input('token')), 403);
    }

    private function freshUserData(): void
    {
        $tables = [
            'personal_access_tokens',
            'user_fcm_tokens',
            'password_reset_tokens',
            'comment_likes',
            'bookmarks',
            'post_tags',
            'post_mentions',
            'story_mentions',
            'story_views',
            'notifications',
            'comments',
            'likes',
            'direct_messages',
            'group_message_reads',
            'group_message_mentions',
            'group_messages',
            'group_members',
            'groups',
            'announcements',
            'portfolios',
            'stories',
            'posts',
            'follows',
            'login_histories',
            'users',
        ];

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');
        } elseif ($driver === 'sqlite') {
            DB::statement('PRAGMA foreign_keys = OFF');
        }

        try {
            foreach ($tables as $table) {
                if (Schema::hasTable($table)) {
                    DB::table($table)->truncate();
                }
            }
        } finally {
            if ($driver === 'mysql') {
                DB::statement('SET FOREIGN_KEY_CHECKS=1');
            } elseif ($driver === 'sqlite') {
                DB::statement('PRAGMA foreign_keys = ON');
            }
        }
    }

    private function parseFile(string $path): array
    {
        $contents = file_get_contents($path);
        $decoded = json_decode($contents, true);

        if (is_array($decoded)) {
            return array_values(isset($decoded['users']) && is_array($decoded['users'])
                ? $decoded['users']
                : $decoded);
        }

        if (preg_match('/\bINSERT\s+INTO\b/i', $contents)) {
            return $this->parseSqlDump($contents);
        }

        $handle = fopen($path, 'r');
        $headers = null;
        $rows = [];
        $delimiter = $this->detectDelimiter($contents);

        while (($columns = fgetcsv($handle, 0, $delimiter)) !== false) {
            if ($this->isIgnorableCsvRow($columns)) {
                continue;
            }

            if ($headers === null) {
                $headers = array_map(fn ($header) => Str::of($header)->replace("\xEF\xBB\xBF", '')->trim()->lower()->toString(), $columns);
                continue;
            }

            if (count(array_filter($columns, fn ($value) => trim((string) $value) !== '')) === 0) {
                continue;
            }

            $values = array_slice(array_pad($columns, count($headers), null), 0, count($headers));
            $rows[] = array_combine($headers, $values);
        }

        fclose($handle);

        return $rows;
    }

    private function isIgnorableCsvRow(array $columns): bool
    {
        $first = trim((string) ($columns[0] ?? ''));

        return $first === ''
            || str_starts_with($first, '--')
            || str_starts_with($first, '#')
            || str_starts_with(strtolower($first), '/*')
            || str_starts_with(strtolower($first), 'set ')
            || str_starts_with(strtolower($first), 'start transaction')
            || str_starts_with(strtolower($first), 'commit')
            || str_starts_with(strtolower($first), 'create table')
            || str_starts_with(strtolower($first), 'drop table')
            || str_starts_with(strtolower($first), 'alter table')
            || str_starts_with(strtolower($first), 'lock tables')
            || str_starts_with(strtolower($first), 'unlock tables');
    }

    private function parseSqlDump(string $contents): array
    {
        $rows = [];
        $pattern = '/INSERT\s+INTO\s+`?[^`\s(]+`?\s*\((.*?)\)\s*VALUES\s*(.*?);/is';

        if (!preg_match_all($pattern, $contents, $matches, PREG_SET_ORDER)) {
            return $rows;
        }

        foreach ($matches as $match) {
            $headers = array_map(
                fn ($header) => Str::of($header)->replace('`', '')->trim()->lower()->toString(),
                str_getcsv($match[1])
            );

            foreach ($this->splitSqlTuples($match[2]) as $tuple) {
                $values = str_getcsv($tuple, ',', "'", "\\");
                $values = array_map(fn ($value) => $this->normalizeSqlValue($value), $values);
                $values = array_slice(array_pad($values, count($headers), null), 0, count($headers));
                $row = array_combine($headers, $values);

                if ($row !== false) {
                    $rows[] = $row;
                }
            }
        }

        return $rows;
    }

    private function splitSqlTuples(string $valuesSql): array
    {
        $tuples = [];
        $buffer = '';
        $depth = 0;
        $inString = false;
        $length = strlen($valuesSql);

        for ($i = 0; $i < $length; $i++) {
            $char = $valuesSql[$i];
            $prev = $i > 0 ? $valuesSql[$i - 1] : '';

            if ($char === "'" && $prev !== '\\') {
                $inString = !$inString;
            }

            if (!$inString && $char === '(') {
                $depth++;
                if ($depth === 1) {
                    $buffer = '';
                    continue;
                }
            }

            if (!$inString && $char === ')') {
                $depth--;
                if ($depth === 0) {
                    $tuples[] = $buffer;
                    $buffer = '';
                    continue;
                }
            }

            if ($depth > 0) {
                $buffer .= $char;
            }
        }

        return $tuples;
    }

    private function normalizeSqlValue(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim($value);

        if (strcasecmp($trimmed, 'NULL') === 0) {
            return null;
        }

        return stripcslashes($trimmed);
    }

    private function detectDelimiter(string $contents): string
    {
        $firstLine = strtok($contents, "\r\n") ?: '';
        return substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
    }

    private function normalizeRow(array $row): array
    {
        $row = array_change_key_case($row, CASE_LOWER);

        return [
            'id' => trim((string) ($row['id'] ?? $row['user_id'] ?? '')),
            'full_name' => trim((string) ($row['full_name'] ?? $row['name'] ?? '')),
            'username' => Str::of((string) ($row['username'] ?? ''))->trim()->lower()->toString(),
            'email' => Str::of((string) ($row['email'] ?? ''))->trim()->lower()->toString(),
            'password' => (string) ($row['password'] ?? $row['password_hash'] ?? ''),
            'created_at' => trim((string) ($row['created_at'] ?? '')),
            'role' => in_array(strtolower((string) ($row['role'] ?? '')), ['teacher', 'parent', 'student'], true)
                ? strtolower((string) $row['role'])
                : null,
        ];
    }

    private function findExistingUser(array $row): ?User
    {
        return User::when($row['id'] !== '', fn ($query) => $query->orWhere('user_id', (int) $row['id']))
            ->when($row['username'] !== '', fn ($query) => $query->orWhere('username', $row['username']))
            ->when($row['email'] !== '', fn ($query) => $query->orWhere('email', $row['email']))
            ->first();
    }

    private function uniqueUsername(string $username, ?int $currentUserId): string
    {
        $base = Str::of($username)->lower()->replaceMatches('/[^a-z0-9._]/', '.')->trim('.')->toString() ?: 'user';
        $candidate = $base;
        $suffix = 2;

        while (User::where('username', $candidate)
            ->when($currentUserId, fn ($query) => $query->where('user_id', '!=', $currentUserId))
            ->exists()) {
            $candidate = "{$base}{$suffix}";
            $suffix++;
        }

        return $candidate;
    }

    private function toPasswordHash(string $password): string
    {
        $info = password_get_info($password);

        return ($info['algoName'] ?? 'unknown') !== 'unknown' ? $password : Hash::make($password);
    }

    private function parseDate(?string $value)
    {
        if (!$value) {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
