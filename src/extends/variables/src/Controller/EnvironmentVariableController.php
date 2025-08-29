<?php

namespace Simp\Core\extends\variables\src\Controller;

use Simp\Core\components\extensions\ModuleHandler;
use Simp\Core\extends\variables\src\Plugin\Variables;
use Simp\Core\lib\themes\View;
use Simp\Core\modules\database\Database;
use Simp\Environment\Environment;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class EnvironmentVariableController
{
    public function index(...$args)
    {

        extract($args);

        if (!$request->isMethod(Request::METHOD_GET)) {
            $results = $this->handleAction($request, $options);
            return new JsonResponse($results);
        }

        $query = Database::database()->con()->prepare("SELECT * FROM environment_variables");
        $query->execute();
        $variables = $query->fetchAll();
        $variables_data = [];
        foreach ($variables as $variable) {
            $variables_data[] = [
                'id' => $variable['id'],
                'name' => $variable['name'],
                'value' => Variables::load($variable['name'])
            ];
        }

        ModuleHandler::factory()->attachLibrary('variables', 'variables.library');;
        return new Response(
            View::view('default.view.variables.dashboard', [
                'variables' => $variables_data,
            ])
        );
    }

    protected function handleAction(Request $request, array $options)
    {
        try{
            if ($request->isMethod(Request::METHOD_POST)) {
                $content_data = json_decode($request->getContent(), true);
                $name = $content_data['name'] ?? '';
                $action = $content_data['action'] ?? '';
                $value = $content_data['value'] ?? '';
                $id = $content_data['id'] ?? '';

                if ($action === 'create' && !empty($name) && !empty($value)) {
                    $name = $this->sanitize_string($name);
                    $query = Database::database()->con()->prepare("INSERT INTO environment_variables (name) VALUES (:name)");
                    $query->bindParam(':name', $name);
                    $result = $query->execute();
                    if ($result) {
                        if (Variables::create($name, $value)) {
                            return ['success'=> true,
                                'message' => 'Variable created successfully',
                                'name' => $name, 'id' => Database::database()->con()->lastInsertId(),
                                'value' => Variables::load($name)
                            ];
                        }
                    }
                }

                else if ($action === 'edit' && !empty($name) && !empty($value) && !empty($id)) {
                    $name = $this->sanitize_string($name);
                    $query = Database::database()->con()->prepare("UPDATE environment_variables SET name = :name WHERE id = :id");
                    $query->bindParam(':name', $name);
                    $query->bindParam(':id', $id, \PDO::PARAM_INT);
                    $result = $query->execute();

                    if ($result) {

                        if (Variables::create($name, $value)) {
                            return ['success'=> true,
                                'message' => 'Variable updated successfully',
                                'name' => $name, 'id' => $id,
                                'value' => Variables::load($name)
                            ];
                        }
                    }
                    return ['success'=> false, 'error' => 'Failed to update variable'];
                }

                else if ($action === 'delete' && !empty($id)) {
                    // select first
                    $query = Database::database()->con()->prepare("SELECT name FROM environment_variables WHERE id = :id");
                    $query->bindParam(':id', $id, \PDO::PARAM_INT);
                    $query->execute();

                    $variable = $query->fetch();
                    $name = $variable['name'] ?? null;
                    if ($name) {
                        $query = Database::database()->con()->prepare("DELETE FROM environment_variables WHERE id = :id");
                        $query->bindParam(':id', $id, \PDO::PARAM_INT);
                        $result = $query->execute();

                        Variables::create($name, '');
                        return ['success'=> true,
                            'message' => 'Variable deleted successfully',
                            'name' => $name, 'id' => $id,
                            'value' => Variables::load($name)
                        ];
                    }
                    return ['success'=> false, 'error' => 'Failed to delete variable'];
                }
                else {
                    return ['success'=> false, 'error' => 'Invalid request'];
                }
            }

        }catch (\Throwable $e){
            return ['success'=> false, 'error' => 'Something went wrong'];
        }
    }

    private function sanitize_string($string) {
        // Replace any non-alphanumeric character with underscore
        $string = preg_replace('/[^A-Za-z0-9]/', '_', $string);

        // Replace multiple consecutive underscores with a single one
        $string = preg_replace('/_+/', '_', $string);

        // Trim underscores from beginning and end (optional)
        $string = trim($string, '_');

        return $string;
    }
}