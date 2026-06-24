<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Import Users</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; background: #f6f7f9; color: #17202a; }
        main { max-width: 760px; margin: 48px auto; background: #fff; padding: 28px; border-radius: 8px; box-shadow: 0 8px 24px rgba(0,0,0,.08); }
        label { display: block; font-weight: 700; margin-top: 18px; }
        input, select { margin-top: 8px; width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #ccd2da; border-radius: 6px; }
        button { margin-top: 22px; padding: 11px 18px; border: 0; border-radius: 6px; background: #0f766e; color: #fff; font-weight: 700; cursor: pointer; }
        code { background: #eef2f7; padding: 2px 5px; border-radius: 4px; }
        .summary { background: #ecfdf5; border: 1px solid #a7f3d0; padding: 12px; border-radius: 6px; margin-bottom: 18px; }
        .errors { background: #fff7ed; border: 1px solid #fed7aa; padding: 12px; border-radius: 6px; margin-top: 18px; }
        li { margin: 6px 0; }
    </style>
</head>
<body>
<main>
    <h1>Import Users</h1>
    <p>Upload CSV, JSON, atau SQL dump phpMyAdmin dengan kolom <code>id</code>, <code>full_name</code>, <code>username</code>, <code>email</code>, <code>password</code>, <code>created_at</code>.</p>

    @if ($summary)
        <div class="summary">
            Created: <strong>{{ $summary['created'] }}</strong>,
            Updated: <strong>{{ $summary['updated'] }}</strong>,
            Skipped: <strong>{{ $summary['skipped'] }}</strong>,
            Mode: <strong>{{ !empty($summary['fresh']) ? 'Fresh import' : 'Update existing' }}</strong>
        </div>
    @endif

    @if ($errors->any())
        <div class="errors">
            <strong>Validasi Laravel:</strong>
            <ul>
                @foreach ($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @if (!empty($importErrors))
        <div class="errors">
            <strong>Catatan import:</strong>
            <ul>
                @foreach ($importErrors as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    <form method="post" action="{{ route('users.import.store') }}" enctype="multipart/form-data">
        @csrf
        <input type="hidden" name="token" value="{{ request('token') }}">

        <label for="users_file">File user</label>
        <input id="users_file" name="users_file" type="file" accept=".csv,.txt,.json,.sql" required>

        <label for="default_role">Default role</label>
        <select id="default_role" name="default_role">
            <option value="student">student</option>
            <option value="teacher">teacher</option>
            <option value="parent">parent</option>
        </select>

        <label>
            <input type="hidden" name="fresh_import" value="0">
            <input type="checkbox" name="fresh_import" value="1" checked style="width:auto;margin-right:8px">
            Fresh import: hapus user dan data terkait dulu agar tidak dobel
        </label>

        <label>
            <input type="hidden" name="verify_email" value="0">
            <input type="checkbox" name="verify_email" value="1" checked style="width:auto;margin-right:8px">
            Tandai email imported sebagai verified
        </label>

        <button type="submit">Upload dan Import</button>
    </form>
</main>
</body>
</html>
