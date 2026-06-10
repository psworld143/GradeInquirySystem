<?php
session_start();
require_once '../config/db.php';

// Check if user is logged in and is a teacher
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'teacher') {
    header("Location: ../login.php");
    exit();
}

// Check if vendor/autoload.php exists
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    die('Please run "composer install" in the teacher directory to install required dependencies.');
}

require 'vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color;
use PhpOffice\PhpSpreadsheet\Cell\DataValidation;

try {
    // Create a new Spreadsheet object
    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();

    // Set column headers
    $sheet->setCellValue('A1', 'Lastname');
    $sheet->setCellValue('B1', 'Firstname');
    $sheet->setCellValue('C1', 'Grade');
    $sheet->setCellValue('D1', 'Remarks');

    // Add instructions in the first data row
    $sheet->setCellValue('A2', 'Instructions:');
    $sheet->setCellValue('B2', 'Fill in student data below this row');
    $sheet->setCellValue('C2', 'Enter grades as numbers');
    $sheet->setCellValue('D2', 'Select "Passed" or "Failed"');

    // Style the header row
    $headerStyle = [
        'font' => [
            'bold' => true,
            'color' => ['rgb' => 'FFFFFF'],
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => '4B6CB7'],
        ],
    ];

    // Style the instructions row
    $instructionStyle = [
        'font' => [
            'bold' => true,
            'italic' => true,
        ],
        'fill' => [
            'fillType' => Fill::FILL_SOLID,
            'startColor' => ['rgb' => 'E8F0FE'],
        ],
    ];

    $sheet->getStyle('A1:D1')->applyFromArray($headerStyle);
    $sheet->getStyle('A2:D2')->applyFromArray($instructionStyle);

    // Add data validation for Remarks column (D)
    $validation = $sheet->getCell('D3')->getDataValidation();
    $validation->setType(DataValidation::TYPE_LIST);
    $validation->setErrorStyle(DataValidation::STYLE_INFORMATION);
    $validation->setAllowBlank(false);
    $validation->setShowInputMessage(true);
    $validation->setShowErrorMessage(true);
    $validation->setShowDropDown(true);
    $validation->setFormula1('"Passed,Failed"');
    $validation->setErrorTitle('Input error');
    $validation->setError('Please select either "Passed" or "Failed"');
    $validation->setPromptTitle('Select Remarks');
    $validation->setPrompt('Please select either "Passed" or "Failed"');

    // Apply the validation to the entire column D starting from row 3
    $sheet->setDataValidation('D3:D1000', $validation);

    // Auto-size columns
    foreach (range('A', 'D') as $col) {
        $sheet->getColumnDimension($col)->setAutoSize(true);
    }

    // Set headers for Excel file download
    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment;filename="GradeImportFormat.xlsx"');
    header('Cache-Control: max-age=0');
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    // Create the Excel file
    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;

} catch (Exception $e) {
    // Log the error
    error_log("Error generating Excel file: " . $e->getMessage());
    
    // Show user-friendly error message
    die('An error occurred while generating the Excel file. Please try again later.');
} 