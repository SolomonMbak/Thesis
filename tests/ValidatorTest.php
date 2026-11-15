<?php
declare(strict_types=1);

namespace Tests;

use App\Core\Validator;

final class ValidatorTest extends TestCase
{
    public function testRequired(): void
    {
        $errors = Validator::validate(['name' => '   '], ['name' => 'required']);
        $this->assertSame('Name is required.', $errors['name'] ?? null);
        $this->assertSame([], Validator::validate(['name' => 'Ada'], ['name' => 'required']));
    }

    public function testMissingFieldIsRequiredError(): void
    {
        $errors = Validator::validate([], ['email' => 'required|email']);
        $this->assertArrayHasKey('email', $errors);
    }

    public function testOptionalEmptyFieldSkipsOtherRules(): void
    {
        $this->assertSame([], Validator::validate(['email' => ''], ['email' => 'email|maxlen:5']));
    }

    public function testEmail(): void
    {
        $this->assertArrayHasKey('email', Validator::validate(['email' => 'not-an-email'], ['email' => 'email']));
        $this->assertSame([], Validator::validate(['email' => 'a.bello@noun.edu.ng'], ['email' => 'email']));
    }

    public function testIntMinMax(): void
    {
        $rules = ['capacity' => 'required|int|min:1|max:500'];
        $this->assertSame('Capacity must be a whole number.', Validator::validate(['capacity' => '12.5'], $rules)['capacity']);
        $this->assertSame('Capacity must be at least 1.', Validator::validate(['capacity' => '0'], $rules)['capacity']);
        $this->assertSame('Capacity must be at most 500.', Validator::validate(['capacity' => '501'], $rules)['capacity']);
        $this->assertSame([], Validator::validate(['capacity' => '120'], $rules));
    }

    public function testLengthRulesCountCharactersNotBytes(): void
    {
        $this->assertSame([], Validator::validate(['n' => 'Ọlá'], ['n' => 'maxlen:3']), 'Three characters, more bytes.');
        $errors = Validator::validate(['password' => 'short'], ['password' => 'minlen:10'], ['password' => 'Password']);
        $this->assertSame('Password must be at least 10 characters.', $errors['password']);
    }

    public function testIn(): void
    {
        $rules = ['room_type' => 'in:lecture,lab'];
        $this->assertSame([], Validator::validate(['room_type' => 'lab'], $rules));
        $this->assertSame(
            'Room type must be one of: lecture, lab.',
            Validator::validate(['room_type' => 'hall'], $rules)['room_type']
        );
    }

    public function testTime(): void
    {
        $rules = ['start' => 'time'];
        $this->assertSame([], Validator::validate(['start' => '08:00'], $rules));
        $this->assertSame([], Validator::validate(['start' => '17:30:00'], $rules));
        $this->assertArrayHasKey('start', Validator::validate(['start' => '25:00'], $rules));
        $this->assertArrayHasKey('start', Validator::validate(['start' => '8am'], $rules));
    }

    public function testCodeRule(): void
    {
        $this->assertSame([], Validator::validate(['code' => 'CIT 301'], ['code' => 'code']));
        $this->assertArrayHasKey('code', Validator::validate(['code' => '<script>'], ['code' => 'code']));
    }

    public function testCustomLabelAndFirstErrorOnly(): void
    {
        $errors = Validator::validate(
            ['size' => 'abc'],
            ['size' => 'required|int|min:1'],
            ['size' => 'Group size']
        );
        $this->assertSame(['size' => 'Group size must be a whole number.'], $errors);
    }

    public function testUnknownRuleIsAProgrammingError(): void
    {
        $this->assertThrows(\InvalidArgumentException::class, fn () => Validator::validate(['a' => 'x'], ['a' => 'bogus']));
    }
}
