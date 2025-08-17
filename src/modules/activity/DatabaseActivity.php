<?php

namespace Simp\Core\modules\activity;
use Throwable;
use Simp\Core\lib\routes\Route;
use Simp\Core\modules\database\Database;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Simp\Core\modules\database\DatabaseRecorder;
use Simp\Core\modules\event_subscriber\EventSubscriber;

final class DatabaseActivity implements EventSubscriber
{
    public function listeners(Request $request, Route $route, ?Response $response): void
    {
        try{
            $current_uri = $request->getRequestUri();
            $records = DatabaseRecorder::getActivity($current_uri);

            if($records) {
                foreach($records as $record) {

                    $query = "INSERT INTO database_activity (query_line, executed_time, path) VALUES (:query_line, :time_executed, :path)";
                    $database = Database::database()->con()->prepare($query);
                    $database->bindParam(':query_line', $record['query']);
                    $database->bindParam(':time_executed', $record['execute_time']);
                    $database->bindParam(':path', $current_uri);
                    $database->execute();
                }
            }
        }catch(Throwable) {}
    }

    public static function factory(): EventSubscriber
    {
        return new DatabaseActivity();
    }
}
