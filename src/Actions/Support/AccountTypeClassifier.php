<?php
namespace hexa_package_instagram\Actions\Support;
final class AccountTypeClassifier
{
    public function classify(array $facts): array
    {
        $rules = (array) config('instagram.account_types', []);
        $text = strtolower(implode(' ', array_map(fn ($key) => (string) ($facts[$key] ?? ''), ['category', 'biography', 'external_url', 'full_name', 'username'])));
        foreach ($rules as $type => $keywords) {
            foreach ($keywords as $keyword) {
                if ($keyword !== '' && str_contains($text, strtolower($keyword))) return ['type' => $type, 'reason' => 'Profile contains '.$keyword.'.'];
            }
        }
        if (!empty($facts['is_business'])) return ['type' => 'company', 'reason' => 'Instagram identifies a business account.'];
        return ['type' => 'unclear', 'reason' => 'Available profile facts do not establish an account type.'];
    }
}
