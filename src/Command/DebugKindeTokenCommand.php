<?php

namespace Habityzer\KindeBundle\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'kinde:debug-token',
    description: 'Debug a Kinde JWT token to see what claims it contains',
)]
class DebugKindeTokenCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('token', InputArgument::REQUIRED, 'The JWT token to debug');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $token = $input->getArgument('token');

        // Remove "Bearer " prefix if present
        if (str_starts_with($token, 'Bearer ')) {
            $token = substr($token, 7);
        }

        // Split JWT into parts
        $parts = explode('.', $token);
        
        if (count($parts) !== 3) {
            $io->error('Invalid JWT format. Expected 3 parts separated by dots.');
            return Command::FAILURE;
        }

        [$headerEncoded, $payloadEncoded, $signatureEncoded] = $parts;

        try {
            // Decode header
            $header = json_decode($this->base64UrlDecode($headerEncoded), true);
            
            // Decode payload
            $payload = json_decode($this->base64UrlDecode($payloadEncoded), true);

            $io->title('🔍 Kinde JWT Token Debug');

            // Header
            $io->section('Header');
            $io->table(
                ['Claim', 'Value'],
                array_map(fn($k, $v) => [$k, is_array($v) ? json_encode($v) : $v], array_keys($header), $header)
            );

            // Payload
            $io->section('Payload (Claims)');
            $io->table(
                ['Claim', 'Value'],
                array_map(fn($k, $v) => [$k, $this->formatValue($v)], array_keys($payload), $payload)
            );

            // Check for email
            $io->section('⚠️ Email Status');
            if (isset($payload['email'])) {
                $io->success('✅ Email found: ' . $payload['email']);
            } else {
                $io->error('❌ Email NOT found in token!');
                $io->warning('Your Kinde token is missing the email claim.');
                
                $io->section('🔧 How to Fix');
                $io->listing([
                    'Option 1: Update Nuxt config to request "email" scope',
                    'Option 2: Configure email scope in Kinde Dashboard',
                    'Option 3: Check if user email is verified in Kinde'
                ]);
                
                $io->text('Add to your Kinde SDK initialization:');
                $io->block("scope: 'openid profile email offline'", null, 'fg=green', '  ', true);
            }

            // Check expiration
            if (isset($payload['exp'])) {
                $exp = new \DateTimeImmutable('@' . $payload['exp']);
                $now = new \DateTimeImmutable();
                
                if ($exp < $now) {
                    $io->warning('Token is EXPIRED: ' . $exp->format('Y-m-d H:i:s'));
                } else {
                    $io->info('Token expires: ' . $exp->format('Y-m-d H:i:s') . ' (' . $this->getTimeAgo($exp) . ')');
                }
            }

            // Signature preview
            $io->section('Signature');
            $io->text('Signature (first 40 chars): ' . substr($signatureEncoded, 0, 40) . '...');

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error('Failed to decode token: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }

    private function base64UrlDecode(string $data): string
    {
        $remainder = strlen($data) % 4;
        if ($remainder) {
            $padlen = 4 - $remainder;
            $data .= str_repeat('=', $padlen);
        }
        return base64_decode(strtr($data, '-_', '+/'));
    }

    private function formatValue($value): string
    {
        if (is_array($value)) {
            return json_encode($value, JSON_PRETTY_PRINT);
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_null($value)) {
            return 'null';
        }
        return (string) $value;
    }

    private function getTimeAgo(\DateTimeImmutable $date): string
    {
        $now = new \DateTimeImmutable();
        $diff = $now->diff($date);
        
        if ($diff->invert) {
            return 'expired';
        }
        
        if ($diff->d > 0) {
            return $diff->d . ' day' . ($diff->d > 1 ? 's' : '') . ' remaining';
        }
        if ($diff->h > 0) {
            return $diff->h . ' hour' . ($diff->h > 1 ? 's' : '') . ' remaining';
        }
        return $diff->i . ' minute' . ($diff->i > 1 ? 's' : '') . ' remaining';
    }
}

