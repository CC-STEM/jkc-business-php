<?php

declare(strict_types=1);

namespace App\Service;

use App\Lib\QCloud\Cos;
use App\Constants\ErrorCode;

class UploadService extends BaseService
{
    /**
     * 腾讯云文件上传
     * @param array $file
     * @param string $extension
     * @return array
     * @throws \Exception
     */
    public function cosUpload(array $file,string $extension): array
    {
        $region = 'ap-shanghai';
        $bucket = 'jkc-1313504415';
        $cos = new Cos($bucket,$region);
        $fileName = $cos->generateName();
        $fileName = "{$fileName}.{$extension}";;
        $date = date('Y-m-d');
        $key = "upload/{$date}/{$fileName}";
        $result = $cos->putObject($file,$key);

        return ['code' => ErrorCode::SUCCESS, 'msg' => '', 'data' => $result['data']];
    }
}