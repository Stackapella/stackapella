<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Support\Ops\TempFiles;
use Tests\TestCase;

final class TempFilesTest extends TestCase
{
    public function test_only_our_old_files_and_folders_are_swept(): void
    {
        $folder = sys_get_temp_dir().'/sweep-'.bin2hex(random_bytes(4));
        mkdir($folder.'/share-video-old', 0777, true);
        touch($folder.'/share-video-old/frame.png', time() - 7200);
        touch($folder.'/share-video-old', time() - 7200);
        touch($folder.'/takeAbc', time() - 7200);
        touch($folder.'/takeNew');
        touch($folder.'/someone-elses', time() - 7200);

        $this->assertSame(2, TempFiles::sweep($folder, time() - 3600));
        $this->assertFileDoesNotExist($folder.'/takeAbc');
        $this->assertDirectoryDoesNotExist($folder.'/share-video-old');
        $this->assertFileExists($folder.'/takeNew');
        $this->assertFileExists($folder.'/someone-elses');

        array_map('unlink', glob($folder.'/*') ?: []);
        rmdir($folder);
    }
}
