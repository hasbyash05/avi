<?php
session_start();

$data_file = 'data.json';
$template_file = 'template.html';
$output_file = 'index.html';
$upload_dir = 'uploads/';

// --- 1. Login Handling ---
if (isset($_POST['password'])) {
    if (hash('sha256', $_POST['password']) === '715bce074fd046600e81a27a1f80a3cf6b1a6ed934aeac273480c1f6496a924a') {
        $_SESSION['admin_logged_in'] = true;
        header("Location: admin.php");
        exit;
    } else {
        $login_error = "Password salah!";
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: admin.php");
    exit;
}

if (!isset($_SESSION['admin_logged_in'])) {
    ?>
    <!DOCTYPE html>
    <html lang="id">

    <head>
        <meta charset="UTF-8">
        <title>Admin Login - Aksara Vidya</title>
        <style>
            body {
                font-family: sans-serif;
                background: #f8fafc;
                display: flex;
                justify-content: center;
                align-items: center;
                height: 100vh;
                margin: 0;
            }

            .login-box {
                background: white;
                padding: 2.5rem;
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
                text-align: center;
                width: 100%;
                max-width: 400px;
            }

            input[type=password] {
                padding: 12px;
                width: 100%;
                box-sizing: border-box;
                margin-bottom: 1rem;
                border: 1px solid #cbd5e1;
                border-radius: 6px;
                font-size: 1rem;
            }

            button {
                background: #0a1628;
                color: white;
                border: none;
                padding: 12px 20px;
                border-radius: 6px;
                cursor: pointer;
                width: 100%;
                font-size: 1rem;
                font-weight: 600;
            }

            button:hover {
                background: #1a365d;
            }

            .error {
                color: #ef4444;
                margin-bottom: 1rem;
                font-size: 0.9em;
            }
        </style>
        <link rel="icon" type="image/png" href="logo.png">
    </head>

    <body>
        <div class="login-box">
            <h2 style="margin-top: 0; color: #0a1628;">Admin Panel</h2>
            <p style="color: #64748b; margin-bottom: 2rem;">Masuk untuk mengedit website</p>
            <?php if (isset($login_error))
                echo "<div class='error'>$login_error</div>"; ?>
            <form method="POST">
                <input type="password" name="password" placeholder="Password" required>
                <button type="submit">Login</button>
            </form>
        </div>
    </body>

    </html>
    <?php
    exit;
}

// --- 2. Read Data ---
$data = json_decode(file_get_contents($data_file), true);
if (!$data)
    $data = [];

// --- 3. Save Handling ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save') {

    // Create upload dir if not exists
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    // Update text data
    foreach ($_POST as $key => $value) {
        if ($key !== 'action' && array_key_exists($key, $data)) {
            $data[$key] = stripslashes($value);
        }
    }

    // Handle File Uploads
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    foreach ($_FILES as $key => $file) {
        if ($key === 'mentor_img')
            continue;
        if ($file['error'] === UPLOAD_ERR_OK) {
            if (in_array($file['type'], $allowedTypes)) {
                $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
                $filename = $key . '_' . time() . '.' . $ext;
                $filepath = $upload_dir . $filename;

                if (move_uploaded_file($file['tmp_name'], $filepath)) {
                    $data[$key] = $filepath;
                }
            }
        }
    }




    // Handle Mentors Array
    if (isset($_POST['mentor_name']) && is_array($_POST['mentor_name'])) {
        $mentors = [];
        $count = count($_POST['mentor_name']);
        for ($i = 0; $i < $count; $i++) {
            $mentor = [
                'name' => stripslashes($_POST['mentor_name'][$i]),
                'spec' => stripslashes($_POST['mentor_spec'][$i]),
                'desc' => stripslashes($_POST['mentor_item_desc'][$i]),
                'img' => isset($_POST['existing_mentor_img'][$i]) ? $_POST['existing_mentor_img'][$i] : 'https://placehold.co/150'
            ];

            // Handle uploaded image for this mentor
            if (isset($_FILES['mentor_img']['name'][$i]) && $_FILES['mentor_img']['error'][$i] === UPLOAD_ERR_OK) {
                $ext = pathinfo($_FILES['mentor_img']['name'][$i], PATHINFO_EXTENSION);
                $filename = 'mentor_' . time() . '_' . $i . '.' . $ext;
                $filepath = $upload_dir . $filename;
                if (move_uploaded_file($_FILES['mentor_img']['tmp_name'][$i], $filepath)) {
                    $mentor['img'] = $filepath;
                }
            }
            $mentors[] = $mentor;
        }
        $data['mentors'] = $mentors;
    }

    // Save data.json
    file_put_contents($data_file, json_encode($data, JSON_PRETTY_PRINT));

    // GENERATE index.html
    $template = file_get_contents($template_file);

    // Generate Mentors HTML
    $mentors_html = '';
    if (isset($data['mentors']) && is_array($data['mentors'])) {
        $delay = 1;
        foreach ($data['mentors'] as $mentor) {
            $mentors_html .= '
                    <div class="glass-card mentor-card-new fade-up delay-' . $delay . '">
                        <img src="' . $mentor['img'] . '" alt="' . $mentor['name'] . '" class="mentor-img">
                        <div class="mentor-info">
                            <h4>' . $mentor['name'] . '</h4>
                            <span class="mentor-specialty"><i class="fa-solid fa-briefcase"></i> ' . $mentor['spec'] . '</span>
                            <p>' . $mentor['desc'] . '</p>
                        </div>
                    </div>';
            $delay++;
            if ($delay > 3)
                $delay = 1;
        }
    }
    $template = str_replace('{{mentors_html}}', $mentors_html, $template);

    foreach ($data as $key => $value) {
        if (is_array($value))
            continue;
        $template = str_replace('{{' . $key . '}}', $value, $template);
    }
    file_put_contents($output_file, $template);

    header("Location: admin.php?success=1");
    exit;
}

// --- 4. Admin Dashboard UI ---
?>
<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">
    <title>Dashboard CMS - Aksara Vidya</title>
    <style>
        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f1f5f9;
            margin: 0;
            color: #334155;
        }

        .header {
            background: #0a1628;
            color: white;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .header a {
            color: #cbd5e1;
            text-decoration: none;
            margin-left: 1.5rem;
            font-weight: 500;
        }

        .header a:hover {
            color: white;
        }

        .header .btn-view {
            background: #3b82f6;
            padding: 8px 16px;
            border-radius: 6px;
            color: white;
        }

        .container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
            display: flex;
            gap: 2rem;
        }

        .sidebar {
            width: 250px;
            flex-shrink: 0;
        }

        .sidebar-menu {
            background: white;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            position: sticky;
            top: 100px;
        }

        .sidebar-menu a {
            display: block;
            padding: 12px 20px;
            color: #475569;
            text-decoration: none;
            border-bottom: 1px solid #f1f5f9;
            font-weight: 500;
        }

        .sidebar-menu a:hover,
        .sidebar-menu a.active {
            background: #f8fafc;
            color: #2563eb;
            border-left: 4px solid #2563eb;
        }

        .content {
            flex-grow: 1;
            background: white;
            border-radius: 8px;
            padding: 2rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }

        .section-group {
            display: none;
        }

        .section-group.active {
            display: block;
        }

        .section-title {
            font-size: 1.5rem;
            color: #0f172a;
            margin-top: 0;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #334155;
        }

        .form-control {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.95rem;
        }

        .form-control:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        textarea.form-control {
            min-height: 100px;
            resize: vertical;
        }

        .img-preview {
            max-width: 300px;
            max-height: 200px;
            border-radius: 8px;
            margin-bottom: 10px;
            display: block;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            object-fit: contain;
        }

        .btn-save {
            background: #10b981;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(16, 185, 129, 0.2);
        }

        .btn-save:hover {
            background: #059669;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            padding: 1rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: 1px solid #a7f3d0;
        }

        .float-save {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }

        .float-save button {
            background: #2563eb;
            padding: 15px 30px;
            font-size: 1.1rem;
            border-radius: 50px;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.4);
        }

        .float-save button:hover {
            background: #1d4ed8;
            transform: translateY(-2px);
            transition: 0.2s;
        }
    </style>
    <link rel="icon" type="image/png" href="logo.png">
</head>

<body>
    <div class="header">
        <h2 style="margin: 0;">Aksara Vidya CMS</h2>
        <div>
            <a href="index.html" target="_blank" class="btn-view">Lihat Website</a>
            <a href="?logout=1">Logout</a>
        </div>
    </div>

    <form method="POST" enctype="multipart/form-data">
        <input type="hidden" name="action" value="save">

        <div class="container">
            <div class="sidebar">
                <div class="sidebar-menu">
                    <a href="#" onclick="showGroup('pengaturan', this)" class="active">Pengaturan Umum</a>
                    <a href="#" onclick="showGroup('hero', this)">Beranda (Hero)</a>
                    <a href="#" onclick="showGroup('visimisi', this)">Visi & Misi</a>
                    <a href="#" onclick="showGroup('layanan', this)">Layanan</a>
                    <a href="#" onclick="showGroup('program', this)">Program</a>
                    <a href="#" onclick="showGroup('mentor', this)">Mentor</a>
                    <a href="#" onclick="showGroup('statistik', this)">Statistik & CTA</a>
                </div>
            </div>

            <div class="content">
                <?php if (isset($_GET['success'])): ?>
                    <div class="alert-success">
                        <strong>Berhasil!</strong> Semua perubahan telah disimpan dan website (index.html) berhasil
                        diperbarui.
                    </div>
                <?php endif; ?>

                <!-- PENGATURAN UMUM -->
                <div id="group-pengaturan" class="section-group active">
                    <h3 class="section-title">Pengaturan Umum</h3>
                    <?php renderInput($data, 'site_title', 'Judul Website (SEO)'); ?>
                    <?php renderTextarea($data, 'meta_desc', 'Deskripsi Website (SEO)'); ?>
                    <?php renderInput($data, 'wa_number', 'Nomor WhatsApp (Contoh: 62812...)'); ?>
                    <?php renderInput($data, 'email', 'Alamat Email'); ?>
                    <?php renderInput($data, 'ig_url', 'URL Instagram'); ?>
                    <?php renderInput($data, 'address_text', 'Alamat Fisik'); ?>
                    <?php renderInput($data, 'footer_desc', 'Teks Deskripsi Footer'); ?>
                    <?php renderInput($data, 'footer_copyright', 'Copyright Footer'); ?>

                    <h4 style="margin-top:2rem;">Logo Utama</h4>
                    <?php renderImageUpload($data, 'img_logo', 'Logo Header (Terang)'); ?>
                </div>

                <!-- BERANDA (HERO) -->
                <div id="group-hero" class="section-group">
                    <h3 class="section-title">Bagian Beranda (Atas)</h3>
                    <?php renderImageUpload($data, 'img_hero', 'Gambar Latar Belakang (Hero)'); ?>
                    <?php renderInput($data, 'hero_title', 'Judul Besar'); ?>
                    <?php renderTextarea($data, 'hero_desc', 'Deskripsi Pendek'); ?>
                </div>

                <!-- VISI MISI -->
                <div id="group-visimisi" class="section-group">
                    <h3 class="section-title">Visi & Misi</h3>
                    <?php renderInput($data, 'visimisi_subtitle', 'Sub-judul'); ?>
                    <?php renderInput($data, 'visimisi_title', 'Judul Utama'); ?>
                    <?php renderTextarea($data, 'visimisi_desc', 'Deskripsi Singkat'); ?>
                    <?php renderTextarea($data, 'visi_text', 'Teks Visi Perusahaan'); ?>
                    <hr style="margin:2rem 0; border:0; border-top:1px solid #e2e8f0;">
                    <?php renderInput($data, 'misi_1_title', 'Misi 1: Judul'); ?>
                    <?php renderTextarea($data, 'misi_1_desc', 'Misi 1: Teks'); ?>
                    <?php renderInput($data, 'misi_2_title', 'Misi 2: Judul'); ?>
                    <?php renderTextarea($data, 'misi_2_desc', 'Misi 2: Teks'); ?>
                    <?php renderInput($data, 'misi_3_title', 'Misi 3: Judul'); ?>
                    <?php renderTextarea($data, 'misi_3_desc', 'Misi 3: Teks'); ?>
                    <?php renderInput($data, 'misi_4_title', 'Misi 4: Judul'); ?>
                    <?php renderTextarea($data, 'misi_4_desc', 'Misi 4: Teks'); ?>
                    <?php renderInput($data, 'misi_5_title', 'Misi 5: Judul'); ?>
                    <?php renderTextarea($data, 'misi_5_desc', 'Misi 5: Teks'); ?>
                </div>

                <!-- LAYANAN -->
                <div id="group-layanan" class="section-group">
                    <h3 class="section-title">Layanan Perusahaan</h3>
                    <?php renderInput($data, 'layanan_title', 'Judul Utama'); ?>
                    <?php renderTextarea($data, 'layanan_desc', 'Deskripsi'); ?>

                    <h4 style="margin-top:2rem;">Logo Layanan</h4>
                    <?php renderImageUpload($data, 'img_jurnal_logo', 'Logo Aksara Jurnal'); ?>
                    <?php renderImageUpload($data, 'img_skillup_logo', 'Logo SkillUp Academy'); ?>
                    <?php renderImageUpload($data, 'img_book_logo', 'Logo Aksara Book'); ?>
                    <?php renderImageUpload($data, 'img_fablab_logo', 'Logo Aksara Fablab'); ?>

                    <h4 style="margin-top:2rem;">Layanan 1</h4>
                    <?php renderInput($data, 'layanan_1_title', 'Nama Layanan'); ?>
                    <?php renderInput($data, 'layanan_1_subtitle', 'Sub-judul Layanan'); ?>
                    <?php renderInput($data, 'layanan_1_item_1', 'Poin 1'); ?>
                    <?php renderInput($data, 'layanan_1_item_2', 'Poin 2'); ?>
                    <?php renderInput($data, 'layanan_1_item_3', 'Poin 3'); ?>

                    <h4 style="margin-top:2rem;">Layanan 2</h4>
                    <?php renderInput($data, 'layanan_2_title', 'Nama Layanan'); ?>
                    <?php renderInput($data, 'layanan_2_subtitle', 'Sub-judul Layanan'); ?>
                    <?php renderInput($data, 'layanan_2_item_1', 'Poin 1'); ?>
                    <?php renderInput($data, 'layanan_2_item_2', 'Poin 2'); ?>
                    <?php renderInput($data, 'layanan_2_item_3', 'Poin 3'); ?>

                    <h4 style="margin-top:2rem;">Layanan 3</h4>
                    <?php renderInput($data, 'layanan_3_title', 'Nama Layanan'); ?>
                    <?php renderInput($data, 'layanan_3_subtitle', 'Sub-judul Layanan'); ?>
                    <?php renderInput($data, 'layanan_3_item_1', 'Poin 1'); ?>
                    <?php renderInput($data, 'layanan_3_item_2', 'Poin 2'); ?>
                    <?php renderInput($data, 'layanan_3_item_3', 'Poin 3'); ?>

                    <h4 style="margin-top:2rem;">Layanan 4</h4>
                    <?php renderInput($data, 'layanan_4_title', 'Nama Layanan'); ?>
                    <?php renderInput($data, 'layanan_4_subtitle', 'Sub-judul Layanan'); ?>
                    <?php renderInput($data, 'layanan_4_item_1', 'Poin 1'); ?>
                    <?php renderInput($data, 'layanan_4_item_2', 'Poin 2'); ?>
                    <?php renderInput($data, 'layanan_4_item_3', 'Poin 3'); ?>
                </div>

                <!-- PROGRAM -->
                <div id="group-program" class="section-group">
                    <h3 class="section-title">Program Unggulan</h3>
                    <?php renderInput($data, 'program_title', 'Judul Utama'); ?>
                    <?php renderTextarea($data, 'program_desc', 'Deskripsi'); ?>

                    <h4 style="margin-top:2rem;">Program 1 & 2</h4>
                    <?php renderInput($data, 'prog_1_title', 'Judul Prog 1'); ?>
                    <?php renderTextarea($data, 'prog_1_desc', 'Teks Prog 1'); ?>
                    <?php renderInput($data, 'prog_2_title', 'Judul Prog 2'); ?>
                    <?php renderTextarea($data, 'prog_2_desc', 'Teks Prog 2'); ?>

                    <h4 style="margin-top:2rem;">Program 3 & 4</h4>
                    <?php renderInput($data, 'prog_3_title', 'Judul Prog 3'); ?>
                    <?php renderTextarea($data, 'prog_3_desc', 'Teks Prog 3'); ?>
                    <?php renderInput($data, 'prog_4_title', 'Judul Prog 4'); ?>
                    <?php renderTextarea($data, 'prog_4_desc', 'Teks Prog 4'); ?>

                    <h4 style="margin-top:2rem;">Program 5 & 6</h4>
                    <?php renderInput($data, 'prog_5_title', 'Judul Prog 5'); ?>
                    <?php renderTextarea($data, 'prog_5_desc', 'Teks Prog 5'); ?>
                    <?php renderInput($data, 'prog_6_title', 'Judul Prog 6'); ?>
                    <?php renderTextarea($data, 'prog_6_desc', 'Teks Prog 6'); ?>
                </div>

                <!-- MENTOR -->
                <div id="group-mentor" class="section-group">
                    <h3 class="section-title">Mentor & Fasilitator</h3>
                    <?php renderInput($data, 'mentor_title', 'Judul Utama'); ?>
                    <?php renderTextarea($data, 'mentor_desc', 'Deskripsi'); ?>

                    <div id="mentors-container">
                        <?php
                        $mentors = isset($data['mentors']) ? $data['mentors'] : [];
                        foreach ($mentors as $index => $mentor):
                            ?>
                            <div class="mentor-block"
                                style="border: 1px solid #e2e8f0; padding: 1rem; border-radius: 8px; margin-top: 1rem; position: relative;">
                                <button type="button" onclick="this.parentNode.remove()"
                                    style="position:absolute; top: 1rem; right: 1rem; background: #ef4444; color: white; border: none; border-radius: 4px; padding: 0.5rem; cursor: pointer;"><i
                                        class="fa-solid fa-trash"></i> Hapus</button>
                                <h4 style="margin-top:0;">Mentor <?= $index + 1 ?></h4>
                                <div class="form-group">
                                    <label>Foto Mentor</label>
                                    <?php if ($mentor['img']): ?>
                                        <div style="margin-bottom: 0.5rem;"><img src="<?= htmlentities($mentor['img']) ?>"
                                                style="height: 100px; border-radius: 8px; object-fit: cover;"></div>
                                    <?php endif; ?>
                                    <input type="hidden" name="existing_mentor_img[]"
                                        value="<?= htmlentities($mentor['img']) ?>">
                                    <input type="file" name="mentor_img[]" accept="image/*">
                                </div>
                                <div class="form-group">
                                    <label>Nama Lengkap</label>
                                    <input type="text" name="mentor_name[]" value="<?= htmlentities($mentor['name']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Spesialisasi / Keahlian</label>
                                    <input type="text" name="mentor_spec[]" value="<?= htmlentities($mentor['spec']) ?>">
                                </div>
                                <div class="form-group">
                                    <label>Deskripsi Singkat</label>
                                    <textarea name="mentor_item_desc[]"><?= htmlentities($mentor['desc']) ?></textarea>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" onclick="addMentor()"
                        style="margin-top: 1rem; background: #10b981; color: white; padding: 0.5rem 1rem; border: none; border-radius: 4px; cursor: pointer;"><i
                            class="fa-solid fa-plus"></i> Tambah Mentor</button>

                    <script>
                        function addMentor() {
                            const container = document.getElementById('mentors-container');
                            const index = container.children.length + 1;
                            const html = `
                        <div class="mentor-block" style="border: 1px solid #e2e8f0; padding: 1rem; border-radius: 8px; margin-top: 1rem; position: relative;">
                            <button type="button" onclick="this.parentNode.remove()" style="position:absolute; top: 1rem; right: 1rem; background: #ef4444; color: white; border: none; border-radius: 4px; padding: 0.5rem; cursor: pointer;"><i class="fa-solid fa-trash"></i> Hapus</button>
                            <h4 style="margin-top:0;">Mentor Baru</h4>
                            <div class="form-group">
                                <label>Foto Mentor</label>
                                <input type="hidden" name="existing_mentor_img[]" value="https://placehold.co/150">
                                <input type="file" name="mentor_img[]" accept="image/*">
                            </div>
                            <div class="form-group">
                                <label>Nama Lengkap</label>
                                <input type="text" name="mentor_name[]" value="">
                            </div>
                            <div class="form-group">
                                <label>Spesialisasi / Keahlian</label>
                                <input type="text" name="mentor_spec[]" value="">
                            </div>
                            <div class="form-group">
                                <label>Deskripsi Singkat</label>
                                <textarea name="mentor_item_desc[]"></textarea>
                            </div>
                        </div>`;
                            container.insertAdjacentHTML('beforeend', html);
                        }
                    </script>
                </div>

                <!-- STATISTIK -->
                <div id="group-statistik" class="section-group">
                    <h3 class="section-title">Statistik & Kontak (CTA)</h3>

                    <h4 style="margin-top:1rem;">Angka Pencapaian</h4>
                    <div style="display: flex; gap: 1rem;">
                        <div style="flex: 1;">
                            <?php renderInput($data, 'stat_1_num', 'Angka 1'); ?><?php renderInput($data, 'stat_1_desc', 'Teks 1'); ?>
                        </div>
                        <div style="flex: 1;">
                            <?php renderInput($data, 'stat_2_num', 'Angka 2'); ?><?php renderInput($data, 'stat_2_desc', 'Teks 2'); ?>
                        </div>
                        <div style="flex: 1;">
                            <?php renderInput($data, 'stat_3_num', 'Angka 3'); ?><?php renderInput($data, 'stat_3_desc', 'Teks 3'); ?>
                        </div>
                        <div style="flex: 1;">
                            <?php renderInput($data, 'stat_4_num', 'Angka 4'); ?><?php renderInput($data, 'stat_4_desc', 'Teks 4'); ?>
                        </div>
                    </div>

                    <h4 style="margin-top:2rem;">Call To Action (Kontak)</h4>
                    <?php renderInput($data, 'cta_title', 'Judul Aakan (CTA)'); ?>
                    <?php renderTextarea($data, 'cta_desc', 'Teks Ajakan'); ?>
                </div>

                <div style="margin-top: 3rem; padding-top: 1.5rem; border-top: 1px solid #e2e8f0;">
                    <button type="submit" class="btn-save" style="width: 100%;">SIMPAN PERUBAHAN</button>
                    <p style="text-align: center; color: #64748b; font-size: 0.85rem; margin-top: 1rem;">Mengeklik
                        simpan akan langsung memperbarui website utama Anda (index.html).</p>
                </div>

                <div class="float-save">
                    <button type="submit" class="btn-save"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
                </div>

            </div>
        </div>
    </form>

    <script>
        function showGroup(id, el) {
            document.querySelectorAll('.section-group').forEach(el => el.classList.remove('active'));
            document.querySelectorAll('.sidebar-menu a').forEach(el => el.classList.remove('active'));
            document.getElementById('group-' + id).classList.add('active');
            el.classList.add('active');
        }

        // Preview image before upload
        function previewImage(input, imgId) {
            if (input.files && input.files[0]) {
                var reader = new FileReader();
                reader.onload = function (e) {
                    document.getElementById(imgId).src = e.target.result;
                }
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

    <!-- FontAwesome for icon in button -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</body>

</html>

<?php
// Helper Functions for UI Rendering
function renderInput($data, $key, $label)
{
    $val = isset($data[$key]) ? htmlspecialchars($data[$key]) : '';
    echo "<div class='form-group'>
            <label>{$label}</label>
            <input type='text' class='form-control' name='{$key}' value=\"{$val}\">
          </div>";
}

function renderTextarea($data, $key, $label)
{
    $val = isset($data[$key]) ? htmlspecialchars($data[$key]) : '';
    echo "<div class='form-group'>
            <label>{$label}</label>
            <textarea class='form-control' name='{$key}'>{$val}</textarea>
          </div>";
}

function renderImageUpload($data, $key, $label)
{
    $val = isset($data[$key]) ? htmlspecialchars($data[$key]) : 'https://placehold.co/400x300?text=Gambar';
    echo "<div class='form-group'>
            <label>{$label}</label>
            <img src='{$val}' class='img-preview' id='preview_{$key}'>
            <input type='file' name='{$key}' accept='image/*' onchange=\"previewImage(this, 'preview_{$key}')\">
            <input type='hidden' name='{$key}' value=\"{$val}\">
          </div>";
}
?>