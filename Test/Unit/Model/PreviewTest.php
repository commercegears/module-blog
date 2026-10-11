<?php
/**
 * @category  Softaware
 * @package   Softaware_Blog
 * @copyright Copyright © Softaware Commerce Ltd (https://softawarecommerce.com/)
 */
declare(strict_types=1);

namespace Softaware\Blog\Test\Unit\Model;

use Magento\Framework\App\DeploymentConfig;
use PHPUnit\Framework\TestCase;
use Softaware\Blog\Model\Preview;

class PreviewTest extends TestCase
{
    private function preview(string $key = 'secret-key'): Preview
    {
        $config = $this->createMock(DeploymentConfig::class);
        $config->method('get')->willReturn($key);
        return new Preview($config);
    }

    public function testTokenIsBoundToPostKeyAndTime(): void
    {
        $now = 1800000000;
        $token = $this->preview()->createToken(5, $now);
        self::assertTrue($this->preview()->isValid(5, $token, $now + 60));
        self::assertFalse($this->preview()->isValid(6, $token, $now + 60));
        self::assertFalse($this->preview('other-key')->isValid(5, $token, $now + 60));
        self::assertFalse($this->preview()->isValid(5, $token, $now + 86401));
        self::assertFalse($this->preview()->isValid(5, 'garbage', $now));
    }
}
