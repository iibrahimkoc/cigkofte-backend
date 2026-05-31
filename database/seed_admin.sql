-- İlk giriş için geçici admin kullanıcısı.
-- Eposta: admin@example.com
-- Şifre: password
-- Canlıya aldıktan sonra hemen değiştir.

INSERT INTO Kullanici (Eposta, Sifre, AdSoyad, Rol, Telefon)
VALUES (
    'admin@example.com',
    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    'Sistem Admin',
    'Admin',
    NULL
)
ON DUPLICATE KEY UPDATE Eposta = Eposta;
