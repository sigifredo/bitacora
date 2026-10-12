<?php
declare(strict_types=1);

/** Cliente HTTP mínimo con cookie de sesión propia y la cabecera anti-CSRF del frontend. */
final class Client
{
    private CurlHandle $curl;

    public function __construct(private string $baseUrl)
    {
        $this->curl = curl_init();
        curl_setopt_array($this->curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_COOKIEFILE => '', CURLOPT_HEADER => true]);
    }

    /** Cookies de la sesión actual, para compartirlas con otro cliente. @return array<int, string> */
    public function cookies(): array
    {
        return curl_getinfo($this->curl, CURLINFO_COOKIELIST);
    }

    /** @param array<int, string> $cookies */
    public function importCookies(array $cookies): void
    {
        foreach ($cookies as $cookie) {
            curl_setopt($this->curl, CURLOPT_COOKIELIST, $cookie);
        }
    }

    public function timeout(int $seconds): void
    {
        curl_setopt($this->curl, CURLOPT_TIMEOUT, $seconds);
    }

    public function handle(): CurlHandle
    {
        return $this->curl;
    }

    /** Prepara el handle de curl para una petición sin ejecutarla (para lanzar varias en paralelo). */
    public function prepare(string $method, string $path, ?array $body = null, array $headers = ['X-Requested-With: fetch']): void
    {
        if ($body !== null) {
            $headers[] = 'Content-Type: application/json';
        }
        curl_setopt_array($this->curl, [
            CURLOPT_URL => $this->baseUrl . $path,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body === null ? null : json_encode($body),
        ]);
    }

    /** @return array{status: int, body: mixed, headers: string} Interpreta la respuesta de un handle ya ejecutado. */
    public function result(string $raw): array
    {
        $size = curl_getinfo($this->curl, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($this->curl, CURLINFO_RESPONSE_CODE);
        $json = substr($raw, $size);
        return ['status' => $status, 'body' => json_decode($json, true) ?? $json, 'headers' => substr($raw, 0, $size)];
    }

    /** @return array{status: int, body: mixed, headers: string} */
    public function request(string $method, string $path, ?array $body = null, array $headers = ['X-Requested-With: fetch']): array
    {
        $this->prepare($method, $path, $body, $headers);
        return $this->result((string) curl_exec($this->curl));
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    public function post(string $path, ?array $body = []): array
    {
        return $this->request('POST', $path, $body);
    }

    public function put(string $path, array $body): array
    {
        return $this->request('PUT', $path, $body);
    }

    public function delete(string $path): array
    {
        return $this->request('DELETE', $path);
    }
}
