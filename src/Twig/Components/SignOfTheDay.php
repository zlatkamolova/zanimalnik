<?php

namespace App\Twig\Components;

use App\Repository\SignRoleRepository;
use App\Sign\DailySign;
use App\Sign\DailySignService;
use App\Sign\SignBasis;
use App\Sign\SignInput;
use App\Sign\Zodiac;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\DefaultActionTrait;

/**
 * "Знак на деня": the visitor picks a zodiac sign, a name or a role and gets a colour and a number.
 * The choice lives only in the visitor's cookie (written by the daily-sign Stimulus controller);
 * the server computes the sign on every request and stores nothing.
 */
#[AsLiveComponent]
final class SignOfTheDay
{
    use DefaultActionTrait;

    public const COOKIE = 'zanimalnik_znak';

    #[LiveProp(writable: true)]
    public string $basis = 'zodiac';

    #[LiveProp(writable: true)]
    public string $zodiac = '';

    #[LiveProp(writable: true)]
    public string $name = '';

    #[LiveProp(writable: true)]
    public string $role = '';

    /** The confirmed choice in cookie format ("zodiac:lav"); empty = show the form. */
    #[LiveProp]
    public string $chosen = '';

    /** @var list<string> */
    public array $errors = [];

    /** True only right after "Покажи" so the result animates once. */
    public bool $justRevealed = false;

    public function __construct(
        private readonly DailySignService $signs,
        private readonly SignRoleRepository $roles,
        private readonly RequestStack $requestStack,
    ) {
    }

    public function mount(): void
    {
        $input = SignInput::fromCookie($this->requestStack->getCurrentRequest()?->cookies->get(self::COOKIE));
        if ($input && [] === $this->validationErrors($input->basis, $input->value)) {
            $this->chosen = $input->toCookie();
        }
    }

    #[LiveAction]
    public function reveal(): void
    {
        $basis = SignBasis::tryFrom($this->basis) ?? SignBasis::Zodiac;
        $value = match ($basis) {
            SignBasis::Zodiac => $this->zodiac,
            SignBasis::Name => $this->name,
            SignBasis::Role => $this->role,
        };

        $this->errors = $this->validationErrors($basis, $value);
        if ([] === $this->errors) {
            $this->chosen = (new SignInput($basis, $value))->toCookie();
            $this->justRevealed = true;
        }
    }

    /** "Смени": back to the form; the controller deletes the cookie. */
    #[LiveAction]
    public function change(): void
    {
        if ($input = $this->getInput()) {
            $this->basis = $input->basis->value;
        }
        $this->chosen = '';
    }

    public function getInput(): ?SignInput
    {
        return SignInput::fromCookie($this->chosen);
    }

    public function getSign(): ?DailySign
    {
        $input = $this->getInput();

        return $input ? $this->signs->forToday($input) : null;
    }

    /** Human label of the chosen basis: "Лъв", "Мария", "Скитникът". */
    public function getChosenLabel(): string
    {
        $input = $this->getInput();

        return match ($input?->basis) {
            SignBasis::Zodiac => Zodiac::name($input->value),
            SignBasis::Role => $this->roles->findOneBy(['slug' => $input->value])?->getName() ?? $input->value,
            SignBasis::Name => mb_convert_case($input->value, \MB_CASE_TITLE),
            default => '',
        };
    }

    public function getToday(): \DateTimeImmutable
    {
        return $this->signs->today();
    }

    /** @return list<SignBasis> */
    public function getBases(): array
    {
        return SignBasis::cases();
    }

    /** @return array<string, array{0: string, 1: string}> */
    public function getZodiacSigns(): array
    {
        return Zodiac::SIGNS;
    }

    /** @return list<\App\Entity\SignRole> */
    public function getRoleOptions(): array
    {
        return $this->roles->findAllOrdered();
    }

    public function getNameMaxLength(): int
    {
        return SignInput::NAME_MAX_LENGTH;
    }

    /** @return list<string> */
    private function validationErrors(SignBasis $basis, string $value): array
    {
        $roleSlugs = SignBasis::Role === $basis
            ? array_map(fn ($r) => $r->getSlug(), $this->roles->findAllOrdered())
            : [];

        return SignInput::validate($basis, $value, $roleSlugs);
    }
}
