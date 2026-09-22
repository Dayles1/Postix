<?php

declare(strict_types=1);

namespace Tests\Unit\Telegram;

use App\Application\Telegram\Support\VendorNoticeShield;
use PHPUnit\Framework\TestCase;
use RuntimeException;

final class VendorNoticeShieldTest extends TestCase
{
    private string $vendorDirectory;

    private string $vendorFile;

    protected function setUp(): void
    {
        parent::setUp();

        /*
         * A stand-in for an installed library: what makes a file "not
         * ours" is the /vendor/ segment in its path, so the fixture only
         * has to live under one.
         */
        $this->vendorDirectory = sys_get_temp_dir()
            . DIRECTORY_SEPARATOR
            . 'postix-shield-' . getmypid()
            . DIRECTORY_SEPARATOR
            . 'vendor';

        if (! is_dir($this->vendorDirectory)) {
            mkdir($this->vendorDirectory, 0777, true);
        }

        $this->vendorFile = $this->vendorDirectory . DIRECTORY_SEPARATOR . 'library.php';

        file_put_contents(
            $this->vendorFile,
            '<?php function postix_vendor_notice(string $message): string {'
            . ' trigger_error($message, E_USER_WARNING); return "finished"; }',
        );

        require_once $this->vendorFile;
    }

    protected function tearDown(): void
    {
        @unlink($this->vendorFile);
        @rmdir($this->vendorDirectory);
        @rmdir(dirname($this->vendorDirectory));

        parent::tearDown();
    }

    public function test_a_notice_from_a_library_does_not_stop_the_operation(): void
    {
        $result = VendorNoticeShield::guard(
            'test.operation',
            static fn (): string => postix_vendor_notice('Undefined property: Foo::$class'),
        );

        $this->assertSame('finished', $result);
    }

    public function test_the_operations_return_value_is_passed_through(): void
    {
        $this->assertSame(
            ['users' => []],
            VendorNoticeShield::guard('test.operation', static fn (): array => ['users' => []]),
        );
    }

    public function test_an_error_from_our_own_code_is_left_to_the_previous_handler(): void
    {
        $seen = [];

        set_error_handler(static function (int $severity, string $message) use (&$seen): bool {
            $seen[] = $message;

            return true;
        });

        try {
            VendorNoticeShield::guard('test.operation', static function (): void {
                trigger_error('our own bug', E_USER_WARNING);
            });
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['our own bug'], $seen);
    }

    public function test_the_previous_handler_is_restored_afterwards(): void
    {
        $marker = static fn (): bool => true;

        set_error_handler($marker);

        try {
            VendorNoticeShield::guard('test.operation', static fn (): bool => true);

            $current = set_error_handler(null);

            $this->assertSame($marker, $current);
        } finally {
            restore_error_handler();
            restore_error_handler();
        }
    }

    public function test_the_handler_is_restored_even_when_the_operation_throws(): void
    {
        $marker = static fn (): bool => true;

        set_error_handler($marker);

        try {
            try {
                VendorNoticeShield::guard('test.operation', static function (): void {
                    throw new RuntimeException('resolve failed');
                });

                $this->fail('The exception should have been rethrown.');
            } catch (RuntimeException) {
                // expected: the shield demotes notices, it never swallows
                // a genuine failure.
            }

            $current = set_error_handler(null);

            $this->assertSame($marker, $current);
        } finally {
            restore_error_handler();
            restore_error_handler();
        }
    }
}
