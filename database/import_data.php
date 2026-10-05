<?php
$pdo = require_once '../config/database.php';

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $importType = $_POST['import_type'] ?? 'inspection_items';
    $file = $_FILES['csv_file'];

    if ($file['error'] === UPLOAD_ERR_OK) {
        $handle = fopen($file['tmp_name'], 'r');
        if ($handle !== false) {
            $successCount = 0;
            $errorCount = 0;
            
            // Skip BOM if present
            $bom = fread($handle, 3);
            if ($bom !== "\xEF\xBB\xBF") {
                rewind($handle);
            }

            // Skip header row
            $header = fgetcsv($handle, 1000, ",");
            
            $pdo->beginTransaction();
            try {
                if ($importType === 'inspection_items') {
                    $stmt = $pdo->prepare("
                        INSERT INTO inspection_items 
                        (item_number, item_name, asset_code, asset_id_code, status_usable, status_damaged, status_degraded, status_lost, status_unused, remarks) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                        // Assuming CSV columns align with: item_number, item_name, asset_code, asset_id_code, usable, damaged, degraded, lost, unused, remarks
                        if (count($data) >= 2 && !empty($data[1])) {
                            $stmt->execute([
                                (int)$data[0],
                                $data[1],
                                $data[2] ?? null,
                                $data[3] ?? null,
                                (isset($data[4]) && (strtolower(trim($data[4])) === 'ü' || trim($data[4]) === '1' || strtolower(trim($data[4])) === 'true')) ? 1 : 0,
                                (isset($data[5]) && (strtolower(trim($data[5])) === 'ü' || trim($data[5]) === '1')) ? 1 : 0,
                                (isset($data[6]) && (strtolower(trim($data[6])) === 'ü' || trim($data[6]) === '1')) ? 1 : 0,
                                (isset($data[7]) && (strtolower(trim($data[7])) === 'ü' || trim($data[7]) === '1')) ? 1 : 0,
                                (isset($data[8]) && (strtolower(trim($data[8])) === 'ü' || trim($data[8]) === '1')) ? 1 : 0,
                                $data[9] ?? null
                            ]);
                            $successCount++;
                        }
                    }
                } else if ($importType === 'equipment_registry') {
                    $stmt = $pdo->prepare("
                        INSERT INTO equipment_registry 
                        (category, equipment_name, equipment_code, brand_description, serial_number, unit_price, acquisition_method, document_number, location, remarks, acquisition_date) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                    ");

                    while (($data = fgetcsv($handle, 1000, ",")) !== false) {
                        if (count($data) >= 2 && !empty($data[1])) {
                            $price = !empty($data[5]) ? floatval(str_replace(',', '', $data[5])) : null;
                            $date = !empty($data[10]) ? date('Y-m-d', strtotime($data[10])) : null;
                            
                            $stmt->execute([
                                $data[0] ?? null, // category
                                $data[1], // equipment_name
                                $data[2] ?? null, // equipment_code
                                $data[3] ?? null, // brand_description
                                $data[4] ?? null, // serial_number
                                $price, // unit_price
                                $data[6] ?? null, // acquisition_method
                                $data[7] ?? null, // document_number
                                $data[8] ?? null, // location
                                $data[9] ?? null, // remarks
                                $date // acquisition_date
                            ]);
                            $successCount++;
                        }
                    }
                }
                $pdo->commit();
                $message = "Successfully imported $successCount records!";
                $messageType = "success";
            } catch (\Throwable $e) {
                $pdo->rollBack();
                $message = "Error importing data: " . $e->getMessage();
                $messageType = "error";
            }
            fclose($handle);
        } else {
            $message = "Could not open file.";
            $messageType = "error";
        }
    } else {
        $message = "Upload error code: " . $file['error'];
        $messageType = "error";
    }
}
?>
<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>นำเข้าข้อมูลครุภัณฑ์ (Data Import)</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Prompt:wght@300;400;500;600&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Prompt', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-8">
    <div class="max-w-2xl mx-auto bg-white rounded-xl shadow-md overflow-hidden p-8">
        <h1 class="text-2xl font-bold text-gray-800 mb-6">นำเข้าข้อมูลครุภัณฑ์ (CSV Import)</h1>
        
        <?php if ($message): ?>
            <div class="p-4 mb-6 rounded-md <?php echo $messageType === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" class="space-y-6">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">ประเภทข้อมูลที่ต้องการนำเข้า (Import Type)</label>
                <select name="import_type" class="w-full border-gray-300 rounded-md shadow-sm focus:border-indigo-500 focus:ring-indigo-500 p-2 border">
                    <option value="inspection_items">บัญชีรายการตรวจสอบครุภัณฑ์ประจำปี (Inspection Items)</option>
                    <option value="equipment_registry">ทะเบียนครุภัณฑ์ (Equipment Registry)</option>
                </select>
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">ไฟล์ CSV (CSV File)</label>
                <input type="file" name="csv_file" accept=".csv" required class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-md file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 border border-gray-300 rounded-md p-2">
                <p class="mt-2 text-sm text-gray-500">กรุณาตรวจสอบว่าไฟล์ CSV มีโครงสร้างคอลัมน์ที่ถูกต้องและใช้การเข้ารหัส UTF-8</p>
            </div>

            <button type="submit" class="w-full flex justify-center py-2 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                นำเข้าข้อมูล (Import)
            </button>
        </form>

        <div class="mt-8 border-t pt-6">
            <h2 class="text-lg font-medium text-gray-800 mb-4">รูปแบบคอลัมน์ CSV (CSV Format)</h2>
            <div class="space-y-4 text-sm text-gray-600">
                <div>
                    <strong class="text-gray-800">Inspection Items:</strong><br>
                    item_number, item_name, asset_code, asset_id_code, status_usable, status_damaged, status_degraded, status_lost, status_unused, remarks
                </div>
                <div>
                    <strong class="text-gray-800">Equipment Registry:</strong><br>
                    category, equipment_name, equipment_code, brand_description, serial_number, unit_price, acquisition_method, document_number, location, remarks, acquisition_date
                </div>
            </div>
        </div>
    </div>
</body>
</html>
