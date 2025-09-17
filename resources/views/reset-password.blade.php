<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password | Buat Kata Sandi Baru</title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🔒</text></svg>">
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#EDA130',
                        'primary-dark': '#D48A20',
                        success: '#10B981',
                        'success-dark': '#059669',
                    },
                    animation: {
                        'bounce-slow': 'bounce 3s infinite',
                        'float': 'float 6s ease-in-out infinite',
                    },
                    keyframes: {
                        float: {
                            '0%, 100%': { transform: 'translateY(0)' },
                            '50%': { transform: 'translateY(-10px)' },
                        }
                    }
                }
            }
        }
    </script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap');
        
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fdf6e3 0%, #faf5f0 100%);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        
        .circle-blob {
            position: absolute;
            border-radius: 50%;
            opacity: 0.2;
            filter: blur(40px);
            z-index: -1;
        }
        
        .blob-1 {
            width: 400px;
            height: 400px;
            background: #EDA130;
            top: -150px;
            left: -150px;
        }
        
        .blob-2 {
            width: 500px;
            height: 500px;
            background: #10B981;
            bottom: -200px;
            right: -200px;
        }
        
        .card {
            backdrop-filter: blur(10px);
            background: rgba(255, 255, 255, 0.8);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.1);
            border-radius: 20px;
            border: 1px solid rgba(255, 255, 255, 0.5);
        }
        
        .lock-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto;
            position: relative;
            color: #EDA130;
        }
        
        button.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            border: 3px solid rgba(255, 255, 255, 0.5);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        button.loading span { visibility: hidden; }
        @keyframes spin { to { transform: translate(-50%, -50%) rotate(360deg); } }

        .message-area {
            min-height: 20px;
            margin-top: 20px;
            font-weight: 500;
            padding: 12px;
            border-radius: 10px;
            font-size: 0.9rem;
            opacity: 0;
            transform: translateY(10px);
            transition: all 0.3s ease;
        }
        .message-area.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .message-area.success { background-color: #10B981; color: #fff; }
        .message-area.error { background-color: #E53E3E; color: #fff; }
        
        .error-message {
            color: #E53E3E;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: none;
        }
    </style>
</head>
<body class="flex items-center justify-center p-4">
    <!-- Background Blobs -->
    <div class="circle-blob blob-1"></div>
    <div class="circle-blob blob-2"></div>
    
    <div class="card max-w-md w-full p-8">
        <!-- Lock Icon -->
        <div class="lock-icon mb-6">
            <svg xmlns="http://www.w3.org/2000/svg" fill="currentColor" viewBox="0 0 24 24">
                <path d="M18 8h-1V6c0-2.76-2.24-5-5-5S7 3.24 7 6v2H6c-1.1 0-2 .9-2 2v10c0 1.1.9 2 2 2h12c1.1 0 2-.9 2-2V10c0-1.1-.9-2-2-2zm-6 9c-1.1 0-2-.9-2-2s.9-2 2-2 2 .9 2 2-.9 2-2 2zm3.1-9H8.9V6c0-1.71 1.39-3.1 3.1-3.1 1.71 0 3.1 1.39 3.1 3.1v2z"/>
            </svg>
        </div>
        
        <!-- Title -->
        <h1 class="text-3xl font-bold text-center text-gray-800 mb-2">Reset Password</h1>
        
        <!-- Message -->
        <p class="text-gray-600 text-center mb-6">
            Silakan masukkan password baru Anda di bawah ini.
        </p>
        
        <!-- Reset Password Form -->
        <form id="resetPasswordForm" method="POST" action="{{ url('/submit-reset-password') }}">
            @csrf
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            
            <div class="mb-4">
                <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password Baru</label>
                <input type="password" id="password" name="password" placeholder="Masukkan password baru" required
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent transition duration-200">
            </div>
            
            <div class="mb-6">
                <label for="password_confirmation" class="block text-sm font-medium text-gray-700 mb-1">Konfirmasi Password</label>
                <input type="password" id="password_confirmation" name="password_confirmation" placeholder="Konfirmasi password baru" required
                    class="w-full px-4 py-3 rounded-lg border border-gray-300 focus:ring-2 focus:ring-primary focus:border-transparent transition duration-200">
                <div id="errorMessage" class="error-message">Konfirmasi password tidak cocok.</div>
            </div>
            
            <button type="submit" id="submitBtn" disabled class="w-full py-3 px-4 bg-primary hover:bg-primary-dark text-white font-semibold rounded-lg shadow-md transition duration-300 transform hover:-translate-y-0.5">
                <span>Ubah Password</span>
            </button>
            
            <div class="message-area mt-4" id="messageArea"></div>
        </form>
        
        <!-- Additional Info -->
        <div class="mt-8 text-center text-sm text-gray-500">
            <p>Butuh bantuan? <a href="https://wa.me/6281350880733" class="text-primary hover:underline">Hubungi dukungan kami</a></p>
        </div>
    </div>
    
    <!-- Floating Elements -->
    <div class="absolute top-10 left-10 animate-float">
        <div class="w-16 h-16 rounded-full bg-primary opacity-10"></div>
    </div>
    <div class="absolute bottom-20 right-16 animate-float" style="animation-delay: 1s;">
        <div class="w-12 h-12 rounded-full bg-green-500 opacity-10"></div>
    </div>
    <div class="absolute top-1/3 right-1/4 animate-float" style="animation-delay: 2s;">
        <div class="w-10 h-10 rounded-full bg-yellow-500 opacity-10"></div>
    </div>
    
    <!-- Animated Circles -->
    <div class="absolute bottom-1/4 left-1/4">
        <div class="w-8 h-8 rounded-full border-4 border-primary border-opacity-30 animate-ping"></div>
    </div>

    <script>
        const resetPasswordForm = document.getElementById('resetPasswordForm');
        const password = document.getElementById('password');
        const confirmPassword = document.getElementById('password_confirmation');
        const errorMessage = document.getElementById('errorMessage');
        const submitBtn = document.getElementById('submitBtn');
        const messageArea = document.getElementById('messageArea');
        
        function validatePasswords() {
            const passVal = password.value;
            const confirmVal = confirmPassword.value;

            if (!passVal || !confirmVal) {
                submitBtn.disabled = true;
                errorMessage.style.display = 'none';
                return;
            }

            if (passVal !== confirmVal) {
                submitBtn.disabled = true;
                errorMessage.style.display = 'block';
            } else {
                submitBtn.disabled = false;
                errorMessage.style.display = 'none';
            }
        }

        function showMessage(message, type) {
            messageArea.textContent = message;
            messageArea.className = `message-area ${type}`;
            if (message) {
                messageArea.classList.add('visible');
            } else {
                messageArea.classList.remove('visible');
            }
        }

        function setLoading(button, isLoading) {
            button.disabled = isLoading;
            if (isLoading) {
                button.classList.add('loading');
            } else {
                button.classList.remove('loading');
            }
        }

        password.addEventListener('input', validatePasswords);
        confirmPassword.addEventListener('input', validatePasswords);

        // Menangani proses Reset Password
        resetPasswordForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const button = resetPasswordForm.querySelector('button');
            setLoading(button, true);
            showMessage('', '');

            const formData = new FormData(resetPasswordForm);
            const resetPasswordApiUrl = resetPasswordForm.action;

            try {
                const response = await fetch(resetPasswordApiUrl, {
                    method: 'POST',
                    headers: { 
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                    },
                    body: formData
                });
                
                const data = await response.json();

                if (response.ok) {
                    showMessage('Password berhasil direset! Silakan login dengan password baru Anda.', 'success');
                    resetPasswordForm.reset();
                    
                    // Redirect ke halaman login setelah 3 detik
                    setTimeout(() => {
                        window.location.href = '/';
                    }, 3000);
                } else {
                    showMessage(data.message || 'Gagal mereset password. Silakan coba lagi.', 'error');
                }
            } catch (error) {
                console.error('Reset Password Error:', error);
                showMessage('Gagal terhubung ke server. Periksa koneksi Anda.', 'error');
            } finally {
                setLoading(button, false);
            }
        });
    </script>
</body>
</html>