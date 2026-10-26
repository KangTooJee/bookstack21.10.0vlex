<?php

namespace BookStack\Uploads;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;

class ExcelConversion
{
    public function run(string $directory, string $input, string $format): string
    {
        mkdir($directory . '/profile/user', 0700, true);
        file_put_contents($directory . '/profile/user/registrymodifications.xcu',
            '<?xml version="1.0"?><oor:items xmlns:oor="http://openoffice.org/2001/registry">' .
            '<item oor:path="/org.openoffice.Office.Common/Security/Scripting"><prop oor:name="MacroSecurityLevel" oor:op="fuse"><value>3</value></prop></item>' .
            '<item oor:path="/org.openoffice.Office.Calc/Content/Update"><prop oor:name="Link" oor:op="fuse"><value>2</value></prop></item></oor:items>');
        $command = [
            'libreoffice', '-env:UserInstallation=file://' . $directory . '/profile',
            '--headless', '--nologo', '--nodefault', '--norestore',
        ];
        $process = new Process(array_merge($command, ['--convert-to', $format, '--outdir', $directory, $input]), $directory);
        $process->setTimeout(60);
        try {
            $process->run();
        } catch (ProcessTimedOutException $exception) {
            throw new RuntimeException('Chuyển đổi Excel mất quá 60 giây. Hãy chia nhỏ file rồi thử lại.');
        }
        $extension = explode(':', $format)[0];
        $output = $directory . '/' . pathinfo($input, PATHINFO_FILENAME) . '.' . $extension;
        if (!$process->isSuccessful() || !is_file($output) || filesize($output) === 0) {
            throw new RuntimeException('Không chuyển đổi được Excel. Hãy kiểm tra file có bị lỗi hoặc đặt mật khẩu không.');
        }
        if (filesize($output) > 30 * 1024 * 1024) {
            throw new RuntimeException('Kết quả Excel vượt quá 30 MB. Hãy chia nhỏ tài liệu.');
        }
        return $output;
    }
}
