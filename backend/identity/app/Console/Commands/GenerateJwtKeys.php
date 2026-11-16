<?php

namespace App\Console\Commands;

use App\Support\JwtKey;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Generates the RSA key pair used to sign the JWT (RS256) and writes it to the
 * env file as single-line "base64:" values.
 */
class GenerateJwtKeys extends Command
{
    protected $signature = 'jwt:keys
        {--env-file= : Arquivo .env a atualizar (padrão: o .env da aplicação)}
        {--bits=2048 : Tamanho da chave RSA}
        {--force : Substitui chaves já existentes (invalida todos os tokens emitidos)}
        {--show-public : Só mostra a chave pública atual (PEM), para entregar aos outros serviços}';

    protected $description = 'Gera o par de chaves RSA do JWT (RS256) e grava no .env';

    public function handle(): int
    {
        if ($this->option('show-public')) {
            return $this->showPublicKey();
        }

        $envFile = $this->option('env-file') ?: $this->laravel->environmentFilePath();

        if (! is_file($envFile)) {
            $this->error("Arquivo não encontrado: {$envFile}");

            return self::FAILURE;
        }

        $contents = (string) file_get_contents($envFile);

        if (preg_match('/^JWT_PRIVATE_KEY=\S+/m', $contents) && ! $this->option('force')) {
            $this->warn('JWT_PRIVATE_KEY já está definida. Use --force para trocar (todos os tokens atuais deixam de valer).');

            return self::FAILURE;
        }

        [$private, $public] = $this->generatePair((int) $this->option('bits'));

        $contents = $this->setEnvValue($contents, 'JWT_ALGO', 'RS256');
        $contents = $this->setEnvValue($contents, 'JWT_PRIVATE_KEY', JwtKey::encode($private));
        $contents = $this->setEnvValue($contents, 'JWT_PUBLIC_KEY', JwtKey::encode($public));

        file_put_contents($envFile, $contents);

        $this->info("Chaves RS256 gravadas em {$envFile}.");
        $this->line('Entregue aos outros serviços só a chave pública: php artisan jwt:keys --show-public');

        return self::SUCCESS;
    }

    private function showPublicKey(): int
    {
        $public = config('jwt.keys.public');

        if (! $public) {
            $this->error('JWT_PUBLIC_KEY não está definida.');

            return self::FAILURE;
        }

        $this->line(trim($public));

        return self::SUCCESS;
    }

    /** @return array{0: string, 1: string} [private PEM, public PEM] */
    private function generatePair(int $bits): array
    {
        if ($bits < 2048) {
            throw new RuntimeException('Use chaves RSA de pelo menos 2048 bits.');
        }

        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => $bits]);

        if ($key === false || ! openssl_pkey_export($key, $private)) {
            throw new RuntimeException('Falha ao gerar a chave RSA (extensão openssl).');
        }

        return [$private, openssl_pkey_get_details($key)['key']];
    }

    private function setEnvValue(string $contents, string $name, string $value): string
    {
        $line = "{$name}={$value}";
        $pattern = '/^'.preg_quote($name, '/').'=.*$/m';

        if (preg_match($pattern, $contents)) {
            return preg_replace_callback($pattern, fn () => $line, $contents, 1);
        }

        return rtrim($contents, "\n")."\n{$line}\n";
    }
}
