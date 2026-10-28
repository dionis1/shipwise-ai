<?php

namespace App\Shared\Presentation;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class Input
{
    /** @param array<string,mixed> $data */
    private function __construct(private array $data, private ValidatorInterface $validator)
    {
    }

    /** @param list<string> $allowed */
    public static function from(Request $request, ValidatorInterface $validator, array $allowed): self
    {
        if (!str_contains($request->headers->get('Content-Type', ''), 'application/json')) {
            throw new BadRequestHttpException('A JSON body is required.');
        }
        try {
            $data = json_decode($request->getContent(), true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new BadRequestHttpException('Malformed JSON.');
        }
        if (!is_array($data) || array_is_list($data) && $data !== [] || array_diff(array_keys($data), $allowed)) {
            throw new BadRequestHttpException('Unexpected request fields.');
        }

        return new self($data, $validator);
    }

    public function string(string $key, int $min = 1, int $max = 255): string
    {
        $value = $this->data[$key] ?? null;
        if (!is_string($value)) {
            throw new BadRequestHttpException('Invalid '.$key.'.');
        }
        $errors = $this->validator->validate($value, [new Assert\Length(min: $min, max: $max)]);
        if (count($errors)) {
            throw new BadRequestHttpException('Invalid '.$key.': '.$errors[0]->getMessage());
        }

        return $value;
    }

    public function email(): string
    {
        $email = $this->string('email', 3, 180);
        if (count($this->validator->validate($email, new Assert\Email()))) {
            throw new BadRequestHttpException('Invalid email address.');
        }

return strtolower(trim($email));
    }

    /**
     * @return list<string> */
    public function strings(string $key): array
    {
        $value = $this->data[$key] ?? null;
        if (!is_array($value) || !array_is_list($value) || count($value) > 100) {
            throw new BadRequestHttpException('Invalid '.$key.'.');
        } foreach ($value as $item) {
            if (!is_string($item) || strlen($item) > 100) {
                throw new BadRequestHttpException('Invalid '.$key.'.');
            }
        }

return $value;
    }
}
