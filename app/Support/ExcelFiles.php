<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ExcelFiles
{
    public static function download(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows) {
            $book = new Spreadsheet;
            $sheet = $book->getActiveSheet();
            $sheet->setTitle('Danh sach');
            $rowNumber = 1;
            foreach ((function () use ($headers, $rows) { yield $headers; yield from $rows; })() as $row) {
                foreach (array_values($row) as $column => $value) {
                    $cell = [$column + 1, $rowNumber];
                    if (is_int($value) || is_float($value)) $sheet->setCellValue($cell, $value);
                    else $sheet->setCellValueExplicit($cell, (string) ($value ?? ''), DataType::TYPE_STRING);
                }
                $rowNumber++;
            }
            $last = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex(count($headers));
            $sheet->getStyle('A1:'.$last.'1')->getFont()->setBold(true)->getColor()->setARGB('FFFFFFFF');
            $sheet->getStyle('A1:'.$last.'1')->getFill()->setFillType('solid')->getStartColor()->setARGB('FF183456');
            $sheet->freezePane('A2');
            $sheet->setAutoFilter('A1:'.$last.max(1, $rowNumber - 1));
            foreach (range(1, count($headers)) as $column) $sheet->getColumnDimensionByColumn($column)->setWidth($column === 1 && $headers[0] === 'STT' ? 8 : 25);
            try { (new Xlsx($book))->save('php://output'); }
            finally { $book->disconnectWorksheets(); }
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'Cache-Control' => 'no-store']);
    }
}
