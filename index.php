<?php
require 'vendor/autoload.php';

use Slim\Factory\AppFactory;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Swagger\Annotations as SWG;

// Инициализация Slim-приложения
$app = AppFactory::create();

// Включаем вывод ошибок
$app->addErrorMiddleware(true, true, true);

// Импортируем базу данных SQLite для простоты
$db = new PDO('sqlite:notebook.db');
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

// Модель для записной книжки
class Notebook {
    public $id;
    public $full_name;
    public $company;
    public $phone;
    public $email;
    public $birth_date;
    public $photo;
}

// GET /api/v1/notebook/
$app->get('/api/v1/notebook/', function (Request $request, Response $response) use ($db) {
    $page = $request->getQueryParams()['page'] ?? 1;
    $perPage = $request->getQueryParams()['per_page'] ?? 10;
    $offset = ($page - 1) * $perPage;

    $stmt = $db->prepare('SELECT * FROM notebooks LIMIT :per_page OFFSET :offset');
    $stmt->bindParam(':per_page', $perPage, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $notebooks = $stmt->fetchAll(PDO::FETCH_CLASS, 'Notebook');

    return $response->withJson($notebooks);
});

// POST /api/v1/notebook/
$app->post('/api/v1/notebook/', function (Request $request, Response $response) use ($db) {
    $data = $request->getParsedBody();

    // Проверка на обязательные поля
    $requiredFields = ['full_name', 'phone', 'email'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return $response->withStatus(400)->withJson(['error' => "The field '$field' is required."]);
        }
    }

    $stmt = $db->prepare('INSERT INTO notebooks (full_name, company, phone, email, birth_date, photo) 
                        VALUES (:full_name, :company, :phone, :email, :birth_date, :photo)');
    $stmt->bindParam(':full_name', $data['full_name']);
    $stmt->bindParam(':company', $data['company']);
    $stmt->bindParam(':phone', $data['phone']);
    $stmt->bindParam(':email', $data['email']);
    $stmt->bindParam(':birth_date', $data['birth_date']);
    $stmt->bindParam(':photo', $data['photo']);
    $stmt->execute();

    $notebookId = $db->lastInsertId();
    $data['id'] = $notebookId;

    return $response->withJson($data)->withStatus(201);
});

// GET /api/v1/notebook/{id}/
$app->get('/api/v1/notebook/{id}/', function (Request $request, Response $response, array $args) use ($db) {
    $stmt = $db->prepare('SELECT * FROM notebooks WHERE id = :id');
    $stmt->bindParam(':id', $args['id'], PDO::PARAM_INT);
    $stmt->execute();
    $notebook = $stmt->fetchObject('Notebook');

    if (!$notebook) {
        return $response->withStatus(404)->withJson(['error' => 'Notebook not found.']);
    }

    return $response->withJson($notebook);
});

// POST /api/v1/notebook/{id}/
$app->post('/api/v1/notebook/{id}/', function (Request $request, Response $response, array $args) use ($db) {
    $data = $request->getParsedBody();

    // Проверка на обязательные поля
    $requiredFields = ['full_name', 'phone', 'email'];
    foreach ($requiredFields as $field) {
        if (empty($data[$field])) {
            return $response->withStatus(400)->withJson(['error' => "The field '$field' is required."]);
        }
    }

    $stmt = $db->prepare('UPDATE notebooks 
                        SET full_name = :full_name, company = :company, phone = :phone, 
                        email = :email, birth_date = :birth_date, photo = :photo 
                        WHERE id = :id');
    $stmt->bindParam(':id', $args['id'], PDO::PARAM_INT);
    $stmt->bindParam(':full_name', $data['full_name']);
    $stmt->bindParam(':company', $data['company']);
    $stmt->bindParam(':phone', $data['phone']);
    $stmt->bindParam(':email', $data['email']);
    $stmt->bindParam(':birth_date', $data['birth_date']);
    $stmt->bindParam(':photo', $data['photo']);
    $stmt->execute();

    return $response->withJson($data);
});

// DELETE /api/v1/notebook/{id}/
$app->delete('/api/v1/notebook/{id}/', function (Request $request, Response $response, array $args) use ($db) {
    $stmt = $db->prepare('DELETE FROM notebooks WHERE id = :id');
    $stmt->bindParam(':id', $args['id'], PDO::PARAM_INT);
    $stmt->execute();

    return $response->withJson(['message' => 'Notebook deleted successfully']);
});

// Создаем Swagger-документацию
$app->get('/api/v1/docs/', function (Request $request, Response $response) use ($app) {
    $swagger = \OpenApi\scan($app->getBasePath());
    return $response->withJson($swagger);
});

$app->run();
