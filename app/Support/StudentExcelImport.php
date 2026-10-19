<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

final class StudentExcelImport
{
    public static function read(string $path, string $track): array
    {
        $reader = new Xlsx;
        $reader->setReadDataOnly(false);
        $reader->setReadEmptyCells(false);
        try {
            $zip = new \ZipArchive;
            if ($zip->open($path) !== true) throw new \RuntimeException('Invalid workbook.');
            try {
                $size = 0;
                for ($i = 0; $i < $zip->numFiles; $i++) $size += $zip->statIndex($i)['size'];
                if ($size > 32 * 1024 * 1024) throw ValidationException::withMessages(['file' => 'File quá lớn sau khi giải nén. Hãy dùng file mẫu và tối đa 1.000 tài khoản.']);
            } finally { $zip->close(); }
            $info = $reader->listWorksheetInfo($path);
            if (!$info) throw new \RuntimeException('No worksheet.');
            if ($info[0]['totalRows'] > 1001 || $info[0]['totalColumns'] > 30) throw ValidationException::withMessages(['file' => 'Mỗi file tối đa 1.000 tài khoản và 30 cột.']);
            $reader->setLoadSheetsOnly($info[0]['worksheetName']);
            $book = $reader->load($path);
        }
        catch (ValidationException $error) { throw $error; }
        catch (\Throwable) { throw ValidationException::withMessages(['file' => 'Không đọc được file Excel. Vui lòng dùng file mẫu .xlsx.']); }
        try {
            $sheet = $book->getActiveSheet();
            $last = $sheet->getHighestDataRow();
            if ($last > 1001) throw ValidationException::withMessages(['file' => 'Mỗi file tối đa 1.000 tài khoản.']);
            $columns = [];
            for ($column = 1; $column <= min(30, $sheet->getHighestDataColumn() ? \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($sheet->getHighestDataColumn()) : 0); $column++) {
                $heading = Str::slug(trim((string) $sheet->getCell([$column, 1])->getValue()), '_');
                $columns[$heading] = $column;
            }
            foreach (['ho_ten', 'ten_dang_nhap'] as $heading) {
                if (!isset($columns[$heading])) throw ValidationException::withMessages(['file' => 'Thiếu cột '.$heading.'. Vui lòng dùng file mẫu.']);
            }
            $rows = []; $seen = []; $errors = [];
            $existing = User::where('login_scope', $track)->pluck('email')->map(fn ($value) => strtolower($value))->flip()->all();
            for ($number = 2; $number <= $last; $number++) {
                $values = [];
                foreach (['ho_ten', 'ten_dang_nhap', 'mat_khau'] as $heading) {
                    if (!isset($columns[$heading])) { $values[$heading] = ''; continue; }
                    $cell = $sheet->getCell([$columns[$heading], $number]);
                    if ($cell->getDataType() === DataType::TYPE_FORMULA) {
                        $errors[] = 'Dòng '.$number.': không dùng công thức trong thông tin tài khoản.';
                        $values[$heading] = '';
                    } else $values[$heading] = trim((string) $cell->getFormattedValue());
                }
                if (!array_filter($values, fn ($value) => $value !== '')) continue;
                $row = ['row' => $number, 'name' => $values['ho_ten'], 'email' => $values['ten_dang_nhap'], 'password' => $values['mat_khau'] === '' ? '123456' : $values['mat_khau']];
                $validator = Validator::make($row, ['name' => 'required|string|max:255', 'email' => ['required', 'string', 'max:255', 'regex:/^[a-zA-Z0-9_.@+\-]+$/'], 'password' => 'required|string|min:4|max:128'], [], ['name' => 'họ tên', 'email' => 'tên đăng nhập', 'password' => 'mật khẩu']);
                foreach ($validator->errors()->all() as $message) $errors[] = 'Dòng '.$number.': '.$message;
                $key = strtolower($row['email']);
                if (isset($seen[$key])) $errors[] = 'Dòng '.$number.': tên đăng nhập trùng với dòng '.$seen[$key].'.';
                if (isset($existing[$key])) $errors[] = 'Dòng '.$number.': tên đăng nhập đã tồn tại trong phân hệ.';
                $seen[$key] = $number;
                $rows[] = $row;
            }
            if ($errors) throw ValidationException::withMessages(['file' => implode("\n", array_slice($errors, 0, 30))]);
            if (!$rows) throw ValidationException::withMessages(['file' => 'File chưa có tài khoản để nhập.']);
            return $rows;
        } finally { $book->disconnectWorksheets(); }
    }
}
