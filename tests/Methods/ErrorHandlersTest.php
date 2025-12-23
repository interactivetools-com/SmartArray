<?php

declare(strict_types=1);

namespace Itools\SmartArray\Tests\Methods;

use Itools\SmartArray\SmartArray;
use Itools\SmartArray\Tests\SmartArrayTestCase;
use RuntimeException;

/**
 * Tests for SmartArray error handler methods.
 *
 * or404($message)   - Sends 404 response and exits if empty
 * orDie($message)   - Dies with message if empty
 * orThrow($message) - Throws RuntimeException if empty
 * orRedirect($url)  - Redirects and exits if empty
 *
 * Note: Methods that call exit()/die() cannot be fully tested in PHPUnit.
 * Even with @runInSeparateProcess, PHPUnit reports "ended unexpectedly".
 * We test the non-exit paths (non-empty arrays) and document the exit behavior.
 */
class ErrorHandlersTest extends SmartArrayTestCase
{

    //region orThrow() - Fully testable (throws exception, doesn't exit)

    public function testOrThrowReturnsThisWhenNotEmpty(): void
    {
        $smartArray = new SmartArray(['item1', 'item2']);
        $result     = $smartArray->orThrow('Should not throw');

        $this->assertSame($smartArray, $result, 'orThrow() should return $this when array is not empty');
    }

    public function testOrThrowThrowsExceptionWhenEmpty(): void
    {
        $smartArray = new SmartArray([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Array is empty');

        $smartArray->orThrow('Array is empty');
    }

    public function testOrThrowHtmlEncodesMessage(): void
    {
        $smartArray = new SmartArray([]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;');

        $smartArray->orThrow('<script>alert("xss")</script>');
    }

    public function testOrThrowChaining(): void
    {
        $smartArray = new SmartArray(['a', 'b', 'c']);

        $result = $smartArray
            ->orThrow('Should not throw')
            ->first();

        $this->assertSame('a', $result, 'orThrow() should allow method chaining');
    }

    //endregion
    //region or404() - Only non-exit path testable

    public function testOr404ReturnsThisWhenNotEmpty(): void
    {
        $smartArray = new SmartArray(['item1', 'item2']);
        $result     = $smartArray->or404('Should not 404');

        $this->assertSame($smartArray, $result, 'or404() should return $this when array is not empty');
    }

    public function testOr404Chaining(): void
    {
        $smartArray = new SmartArray(['a', 'b', 'c']);

        $result = $smartArray
            ->or404('Should not 404')
            ->first();

        $this->assertSame('a', $result, 'or404() should allow method chaining');
    }

    /**
     * Note: Testing empty array behavior is not possible in PHPUnit because
     * or404() calls exit() which terminates the test process unexpectedly.
     * The expected behavior for empty arrays:
     * - Sets HTTP response code to 404
     * - Sends Content-Type: text/html header
     * - Outputs HTML 404 page with message (HTML-encoded)
     * - Calls exit()
     */
    public function testOr404DocumentedBehavior(): void
    {
        // This test documents expected behavior without actually testing exit
        $this->assertTrue(method_exists(SmartArray::class, 'or404'));

        // Verify method signature accepts optional string
        $method = new \ReflectionMethod(SmartArray::class, 'or404');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertTrue($params[0]->allowsNull());
        $this->assertTrue($params[0]->isDefaultValueAvailable());
        $this->assertNull($params[0]->getDefaultValue());
    }

    //endregion
    //region orDie() - Only non-exit path testable

    public function testOrDieReturnsThisWhenNotEmpty(): void
    {
        $smartArray = new SmartArray(['item1', 'item2']);
        $result     = $smartArray->orDie('Should not die');

        $this->assertSame($smartArray, $result, 'orDie() should return $this when array is not empty');
    }

    public function testOrDieChaining(): void
    {
        $smartArray = new SmartArray(['a', 'b', 'c']);

        $result = $smartArray
            ->orDie('Should not die')
            ->first();

        $this->assertSame('a', $result, 'orDie() should allow method chaining');
    }

    /**
     * Note: Testing empty array behavior is not possible in PHPUnit because
     * orDie() calls die() which terminates the test process unexpectedly.
     * The expected behavior for empty arrays:
     * - HTML-encodes the message
     * - Calls die() with the encoded message
     */
    public function testOrDieDocumentedBehavior(): void
    {
        $this->assertTrue(method_exists(SmartArray::class, 'orDie'));

        // Verify method requires string parameter
        $method = new \ReflectionMethod(SmartArray::class, 'orDie');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertFalse($params[0]->allowsNull());
    }

    //endregion
    //region orRedirect() - Headers check makes testing complex

    /**
     * orRedirect() checks headers_sent() unconditionally (fail-fast design).
     * In PHPUnit, headers are always "sent", so we need process isolation
     * to test even the non-empty array path.
     *
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOrRedirectReturnsThisWhenNotEmpty(): void
    {
        $smartArray = new SmartArray(['item1', 'item2']);
        $result     = $smartArray->orRedirect('/login');

        $this->assertSame($smartArray, $result, 'orRedirect() should return $this when array is not empty');
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function testOrRedirectChaining(): void
    {
        $smartArray = new SmartArray(['a', 'b', 'c']);

        $result = $smartArray
            ->orRedirect('/login')
            ->first();

        $this->assertSame('a', $result, 'orRedirect() should allow method chaining');
    }

    /**
     * Test that orRedirect throws when headers already sent.
     * This is the fail-fast check that happens before testing if empty.
     */
    public function testOrRedirectThrowsWhenHeadersSent(): void
    {
        $smartArray = new SmartArray(['item1', 'item2']);

        // In PHPUnit, headers are always sent, so this should throw
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot redirect: headers already sent/');

        $smartArray->orRedirect('/login');
    }

    /**
     * Note: Testing empty array redirect behavior is not possible in PHPUnit
     * because orRedirect() calls exit() which terminates the process.
     * The expected behavior for empty arrays (when headers not sent):
     * - Sets HTTP response code to 302
     * - Sends Location header with the URL
     * - Calls exit()
     */
    public function testOrRedirectDocumentedBehavior(): void
    {
        $this->assertTrue(method_exists(SmartArray::class, 'orRedirect'));

        // Verify method requires string parameter
        $method = new \ReflectionMethod(SmartArray::class, 'orRedirect');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertFalse($params[0]->allowsNull());
    }

    //endregion

}
