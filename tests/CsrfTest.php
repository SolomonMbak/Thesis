<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Csrf;
use App\Core\Request;

final class CsrfTest extends TestCase
{
    public function testTokenIsStableWithinASession(): void
    {
        $store = [];
        $csrf = new Csrf($store);
        $token = $csrf->token();
        $this->assertSame(64, strlen($token), 'Token should be 32 random bytes in hex.');
        $this->assertSame($token, $csrf->token());
        $this->assertSame($token, $store['_csrf_token'], 'Token must be kept in the session store.');
    }

    public function testDifferentSessionsGetDifferentTokens(): void
    {
        $a = [];
        $b = [];
        $this->assertTrue((new Csrf($a))->token() !== (new Csrf($b))->token());
    }

    public function testValidTokenPasses(): void
    {
        $store = [];
        $csrf = new Csrf($store);
        $this->assertTrue($csrf->isValid($csrf->token()));
    }

    public function testWrongMissingOrEmptyTokenFails(): void
    {
        $store = [];
        $csrf = new Csrf($store);
        $token = $csrf->token();
        $this->assertFalse($csrf->isValid(strrev($token)));
        $this->assertFalse($csrf->isValid(null));
        $this->assertFalse($csrf->isValid(''));
    }

    public function testFailsWhenSessionHasNoToken(): void
    {
        $store = [];
        $csrf = new Csrf($store);
        $this->assertFalse($csrf->isValid('anything'), 'No token issued yet, so nothing can match.');
        $this->assertFalse($csrf->isValid(''));
    }

    public function testRequestAcceptsFormFieldOrHeader(): void
    {
        $store = [];
        $csrf = new Csrf($store);
        $token = $csrf->token();

        $form = new Request([], ['_csrf' => $token], ['REQUEST_METHOD' => 'POST']);
        $this->assertTrue($csrf->checkRequest($form), 'Form field should be accepted.');

        $fetch = new Request([], [], ['REQUEST_METHOD' => 'POST', 'HTTP_X_CSRF_TOKEN' => $token]);
        $this->assertTrue($csrf->checkRequest($fetch), 'Header should be accepted.');

        $none = new Request([], [], ['REQUEST_METHOD' => 'POST']);
        $this->assertFalse($csrf->checkRequest($none));

        $forged = new Request([], ['_csrf' => 'forged'], ['REQUEST_METHOD' => 'POST']);
        $this->assertFalse($csrf->checkRequest($forged));
    }

    public function testFieldEscapesToken(): void
    {
        $store = ['_csrf_token' => 'a"b<c'];
        $html = (new Csrf($store))->field();
        $this->assertTrue(str_contains($html, 'value="a&quot;b&lt;c"'), 'Token must be HTML-escaped.');
    }
}
