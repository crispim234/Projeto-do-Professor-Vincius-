<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');

$DB_HOST = 'localhost';
$DB_NAME = 'crud_system';
$DB_USER = 'root';   // ajuste se você definiu outro usuário
$DB_PASS = '';       // ajuste se o root tiver senha
$DB_CHARSET = 'utf8mb4';

// Em desenvolvimento: exibir erros (NUNCA em produção)
ini_set('display_errors', '1');
error_reporting(E_ALL);

function json_response(bool $success, string $message = '', $data = null, int $httpCode = 200): void {
    http_response_code($httpCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function get_pdo(): PDO {
    global $DB_HOST, $DB_NAME, $DB_USER, $DB_PASS, $DB_CHARSET;
    $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset={$DB_CHARSET}";
    try {
        $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,      // throw exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // return associative array
            PDO::ATTR_EMULATE_PREPARES => false,              // native prepared statements
        ]);
        return $pdo;
    } catch (PDOException $e) {
        json_response(false, 'Erro na conexão com o banco: ' . $e->getMessage(), null, 500);
        // ensure static analyzers know this path does not return a PDO
        throw new RuntimeException('Unable to connect to the database', 0, $e);
    }
}

function sanitize_string(?string $s, int $maxLen): string {
    $s = trim((string)$s);
    if (mb_strlen($s) > $maxLen) {
        $s = mb_substr($s, 0, $maxLen);
    }
    return $s;
}

// Lê entrada JSON se POST; para GET, usamos query string
$method = $_SERVER['REQUEST_METHOD'];
$input = null;
if ($method === 'POST') {
    $raw = file_get_contents('php://input');
    if ($raw !== false && strlen($raw) > 0) {
        $input = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            json_response(false, 'JSON inválido no corpo da requisição.', null, 400);
        }
    } else {
        $input = [];
    }
}

$action = $_GET['action'] ?? ($input['action'] ?? null);
if (!$action) {
    json_response(false, 'Ação não informada. Use ?action=read no GET ou { "action": "create|update|delete" } no POST.', null, 400);
}

$pdo = get_pdo();

try {
    switch ($action) {
        case 'read':
            if ($method !== 'GET') {
                json_response(false, 'Use método GET para read.', null, 405);
            }
            $stmt = $pdo->query('SELECT id, nome, email, telefone, criado_em FROM contatos ORDER BY id DESC');
            $rows = $stmt->fetchAll();
            json_response(true, 'Listagem realizada com sucesso.', $rows);
            break;

        case 'create':
            if ($method !== 'POST') {
                json_response(false, 'Use método POST para create.', null, 405);
            }
            $nome = sanitize_string($input['nome'] ?? '', 120);
            $email = sanitize_string($input['email'] ?? '', 180);
            $telefone = sanitize_string($input['telefone'] ?? '', 30);

            if (mb_strlen($nome) < 2) json_response(false, 'Nome inválido.', null, 422);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(false, 'E-mail inválido.', null, 422);

            $sql = 'INSERT INTO contatos (nome, email, telefone) VALUES (:nome, :email, :telefone)';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':nome' => $nome,
                ':email' => $email,
                ':telefone' => $telefone ?: null
            ]);

            $id = (int)$pdo->lastInsertId();
            json_response(true, 'Registro criado com sucesso!', ['id' => $id], 201);
            break;

        case 'update':
            if ($method !== 'POST') {
                json_response(false, 'Use método POST para update.', null, 405);
            }
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            $nome = sanitize_string($input['nome'] ?? '', 120);
            $email = sanitize_string($input['email'] ?? '', 180);
            $telefone = sanitize_string($input['telefone'] ?? '', 30);

            if ($id <= 0) json_response(false, 'ID inválido.', null, 422);
            if (mb_strlen($nome) < 2) json_response(false, 'Nome inválido.', null, 422);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_response(false, 'E-mail inválido.', null, 422);

            $sql = 'UPDATE contatos SET nome = :nome, email = :email, telefone = :telefone WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':id' => $id,
                ':nome' => $nome,
                ':email' => $email,
                ':telefone' => $telefone ?: null
            ]);

            if ($stmt->rowCount() === 0) {
                // Pode ser que os dados sejam os mesmos; ainda assim consideramos sucesso
                json_response(true, 'Nenhuma alteração detectada (dados iguais ou ID inexistente).');
            } else {
                json_response(true, 'Registro atualizado com sucesso!');
            }
            break;

        case 'delete':
            if ($method !== 'POST') {
                json_response(false, 'Use método POST para delete.', null, 405);
            }
            $id = isset($input['id']) ? (int)$input['id'] : 0;
            if ($id <= 0) json_response(false, 'ID inválido.', null, 422);

            $sql = 'DELETE FROM contatos WHERE id = :id';
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':id' => $id]);

            if ($stmt->rowCount() === 0) {
                json_response(false, 'Registro não encontrado para exclusão.', null, 404);
            }
            json_response(true, 'Registro excluído com sucesso!');
            break;

        default:
            json_response(false, 'Ação desconhecida: ' . $action, null, 400);
    }
} catch (PDOException $e) {
    // Erros de banco
    json_response(false, 'Erro de banco: ' . $e->getMessage(), null, 500);
} catch (Throwable $e) {
    json_response(false, 'Erro inesperado: ' . $e->getMessage(), null, 500);
}