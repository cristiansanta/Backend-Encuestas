<?php
///traer la informacion de la base de datos a un modelo

namespace App\Models;

use App\Helpers\RedisHelper;

class TypeinfoModel
{

    public function getTypecourse()
    {
        // Clave de caché para Redis
        $cacheKey = 'getTypecourse_data';

        // Conexión a la base de datos
        $host = $_ENV['HOST_REPLICA'];
        $dbname = $_ENV['DB_REPLICA'];
        $port = $_ENV['PORT_REPLICA'];
        $user = $_ENV['USER_REPLICA'];
        $password = $_ENV['PASS_REPLICA'];

        try {
            // Verificar si los datos están en Redis
            if (RedisHelper::exists($cacheKey)) {
                $cachedData = RedisHelper::get($cacheKey);
                $result = json_decode($cachedData, true);

                // Verificar si la decodificación fue exitosa
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("Error al decodificar datos de Redis: " . json_last_error_msg());
                }
            } else {
                // Creación de conexión a DB
                $conn = new \PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
                $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                // Definición de la consulta
                $sql = "SELECT \"NFS_ID\",\"NFS_NOMBRE\", \"NFS_TIPO_FORMACION\" FROM \"INTEGRACION\".\"NIVEL_FORMACION\"";

                // Preparación y ejecución de la consulta
                $stmt = $conn->prepare($sql);
                $stmt->execute();

                // Guardado de resultado de query
                $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Verificar si el resultado está vacío
                if (empty($result)) {
                    throw new \Exception("No se encontraron datos en la base de datos");
                }

                // Almacenar el resultado en Redis como JSON
                RedisHelper::set($cacheKey, json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            }

            // Retorno de resultado
            return response()->json($result);

        } catch (\Throwable $th) {
            // Captura error
            $capture = $th->getMessage();
            $msjerror = "Error retrieving data: $capture";
            return response()->json(['error' => $msjerror], 500);
        } finally {
            // Cerrar la conexión
            $conn = null;
        }
    }

    //funtions get program the formation
    public function getProgForm()
    {
        // Clave de caché para Redis
        $cacheKey = 'getProgForm_data';

        // Conexión a la base de datos
        $host = $_ENV['HOST_REPLICA'];
        $dbname = $_ENV['DB_REPLICA'];
        $port = $_ENV['PORT_REPLICA'];
        $user = $_ENV['USER_REPLICA'];
        $password = $_ENV['PASS_REPLICA'];

        try {
            // Verificar si los datos están en Redis
            if (RedisHelper::exists($cacheKey)) {
                $cachedData = RedisHelper::get($cacheKey);
                $result = json_decode($cachedData, true);

                // Verificar si la decodificación fue exitosa
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("Error al decodificar datos de Redis: " . json_last_error_msg());
                }
            } else {
                // Creación de conexión a DB
                $conn = new \PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
                $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

                // Definición de la consulta
                $sql = "SELECT \"PRF_ID\",\"PRF_CODIGO\", \"PRF_DENOMINACION\" FROM \"INTEGRACION\".\"V_PROGRAMA_FORMACION_B\"";

                // Preparación y ejecución de la consulta
                $stmt = $conn->prepare($sql);
                $stmt->execute();

                // Guardado de resultado de query
                $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Almacenar el resultado en Redis como JSON
                $encodedResult = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("Error al codificar datos para Redis: " . json_last_error_msg());
                }

                RedisHelper::set($cacheKey, $encodedResult);
            }

            // Retorno de resultado
            return response()->json($result);

        } catch (\Throwable $th) {
            // Captura error
            $capture = $th->getMessage();
            $msjerror = "Error de la BD: $capture";
            return response()->json(['error' => $msjerror], 500);
        } finally {
            // Cerrar la conexión
            $conn = null;
        }
    }

    //functions get regional
    public function getRegional()
{
   
    $cacheKey = 'getRegional_data';
    $host = $_ENV['HOST_REPLICA'];
    $dbname = $_ENV['DB_REPLICA'];
    $port = $_ENV['PORT_REPLICA'];
    $user = $_ENV['USER_REPLICA'];
    $password = $_ENV['PASS_REPLICA'];

    try {
        $conn = new \PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
        $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        // Obtener el hash de los datos actuales de la base de datos
        $hashQuery = "SELECT MD5(string_agg(CAST((\"RGN_ID\" || \"RGN_NOMBRE\") AS TEXT), '')) AS data_hash FROM \"INTEGRACION\".\"REGIONAL\"";
        $currentHash = $conn->query($hashQuery)->fetchColumn();

        $needUpdate = true;
        if (RedisHelper::exists($cacheKey)) {
            $cachedData = RedisHelper::get($cacheKey);
            $cachedResult = json_decode($cachedData, true);

            if (json_last_error() === JSON_ERROR_NONE && isset($cachedResult['hash']) && $cachedResult['hash'] === $currentHash) {
                $needUpdate = false;
                return response()->json($cachedResult['data']);
            }
        }

        if ($needUpdate) {
            $sql = "SELECT \"RGN_ID\", \"RGN_NOMBRE\" FROM \"INTEGRACION\".\"REGIONAL\"";
            $result = $conn->query($sql)->fetchAll(\PDO::FETCH_ASSOC);

            $dataToCache = [
                'data' => $result,
                'hash' => $currentHash
            ];

            $encodedResult = json_encode($dataToCache, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Error al codificar datos para Redis: " . json_last_error_msg());
            }

            RedisHelper::set($cacheKey, $encodedResult, 60); // TTL de 1 hora
            return response()->json($result);
        }
    } catch (\Throwable $th) {
        $msjerror = "Error: " . $th->getMessage();
        return response()->json(['error' => $msjerror], 500);
    } finally {
        $conn = null;
    }
}

    public function getUsuarioslms()
    {
        // Parámetros de paginación
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);

        // Cache key
        $cacheKey = 'getUsuarioslms_data';

        // Conexión a la base de datos
        $host = $_ENV['HOST_REPLICA'];
        $dbname = $_ENV['DB_REPLICA'];
        $port = $_ENV['PORT_REPLICA'];
        $user = $_ENV['USER_REPLICA'];
        $password = $_ENV['PASS_REPLICA'];

        try {
            // Creación de conexión a DB
            $conn = new \PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Verificar si los datos están en Redis
            if (RedisHelper::exists($cacheKey)) {
                $cachedData = RedisHelper::get($cacheKey);
                $result = json_decode($cachedData, true);

                // Verificar si la decodificación fue exitosa
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("Error al decodificar datos de Redis: " . json_last_error_msg());
                }
            } else {
                // Definición de la consulta
                $sql = "SELECT \"LMS_ID\", \"USR_NIS\", \"USR_NUM_DOC\",
                       (\"USR_NOMBRE\" || ' ' || \"USR_APELLIDO\") AS NOMBRE_COMPLETO,
                       \"USR_CORREO\"
                FROM \"INTEGRACION\".\"USUARIO_LMS\"
                WHERE \"LMS_ID\" is not null";

                // Preparación y ejecución de la consulta
                $stmt = $conn->prepare($sql);
                $stmt->execute();
                $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

                // Almacenar el resultado en Redis como JSON con un tiempo de vida "forever"
                $encodedResult = json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    throw new \Exception("Error al codificar datos para Redis: " . json_last_error_msg());
                }

                RedisHelper::set($cacheKey, $encodedResult);
            }

            // Obtener el total de registros
            $total_count = count($result);

            // Cálculo de las páginas
            $total_pages = ceil($total_count / $limit);

            // Aplicar paginación a los datos obtenidos de Redis
            $offset = ($page - 1) * $limit;
            $paginatedResult = array_slice($result, $offset, $limit);

            // Retorno de resultado con información de paginación
            return [
                'data' => $paginatedResult,
                'total_count' => $total_count,
                'total_pages' => $total_pages,
                'current_page' => $page,
            ];

        } catch (\Throwable $th) {
            // Captura error
            $capture = $th->getMessage();
            $msjerror = "Error de la BD: $capture";
            throw $th; // Arroja error
        } finally {
            // Cerrar la conexión
            $conn = null;
        }
    }

    public function getFichas()
    {
        // Parámetros de paginación
        $page = intval($_GET['page'] ?? 1);
        $limit = intval($_GET['limit'] ?? 20);

        // Conexión a la base de datos
        $host = $_ENV['HOST_REPLICA'];
        $dbname = $_ENV['DB_REPLICA'];
        $port = $_ENV['PORT_REPLICA'];
        $user = $_ENV['USER_REPLICA'];
        $password = $_ENV['PASS_REPLICA'];

        try {
            // Crear conexión a la DB
            $conn = new \PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Cache key
            $cacheKey = 'getFichas_data';

            if (RedisHelper::exists($cacheKey)) {
                $result = RedisHelper::get($cacheKey);
                if (is_array($result) && !empty($result)) {
                    // Calcular la paginación sobre los datos almacenados en Redis
                    $total_count = count($result);
                    $total_pages = ceil($total_count / $limit);
                    $offset = ($page - 1) * $limit;
                    $paginatedResult = array_slice($result, $offset, $limit);

                    return [
                        'data' => $paginatedResult,
                        'total_count' => $total_count,
                        'total_pages' => $total_pages,
                        'current_page' => $page,
                        'cache_key' => $cacheKey,
                    ];
                }
            }

            // Definición de la query principal
            $sql = "SELECT \"LMS_ID\", \"FIC_ID\", \"PRF_DENOMINACION\" FROM \"INTEGRACION\".\"V_FICHA_CARACTERIZACION_B\"";

            // Ejecutar la consulta para obtener todos los datos (sin paginación)
            $stmt = $conn->prepare($sql);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Almacenar los datos completos en Redis
            RedisHelper::set($cacheKey, $result);

            // Calcular la paginación sobre los datos obtenidos
            $total_count = count($result);
            $total_pages = ceil($total_count / $limit);
            $offset = ($page - 1) * $limit;
            $paginatedResult = array_slice($result, $offset, $limit);

            return [
                'data' => $paginatedResult,
                'total_count' => $total_count,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'cache_key' => $cacheKey,
            ];

        } catch (\Throwable $th) { // Captura error

            $capture = $th->getMessage();
            $msjerror = "Error de la BD: $capture";
            throw $th; // Arroja error
        } finally {
            // Cerrar la conexión
            $conn = null;
        }
    }

    public function getroles($request)
    {
        // Parámetros de paginación
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;

        // Obtener los roles de la consulta
        $roleIds = $request->query('roleid');
        $roleIds = explode(',', $roleIds);
        $roleIds = array_map('intval', $roleIds);

        // Conexión a la base de datos
        $host = env('HOST_REPLICA');
        $dbname = env('DB_REPLICA');
        $port = env('PORT_REPLICA');
        $user = env('USER_REPLICA');
        $password = env('PASS_REPLICA');

        try {
            // Crear conexión a la DB
            $conn = new \PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Definición de la consulta principal
            $sql = "SELECT UL.\"USR_CORREO\", ULEC.\"roleid\"
                    FROM \"INTEGRACION\".\"USUARIO_LMS\" UL
                    INNER JOIN \"INTEGRACION\".\"USUARIO_LMS_ENROLL_C\" ULEC
                    ON UL.\"LMS_ID\"=ULEC.userid
                    WHERE UL.\"LMS_ESTADO\"=2 AND ULEC.\"roleid\" IN (" . implode(',', array_fill(0, count($roleIds), '?')) . ")";

            // Consulta para contar el total de registros
            $countSql = "SELECT COUNT(*) FROM ($sql) AS total";
            $stmt = $conn->prepare($countSql);
            $stmt->execute($roleIds);
            $total_count = $stmt->fetchColumn();

            // Cálculo de las páginas
            $total_pages = ceil($total_count / $limit);
            $offset = ($page - 1) * $limit;

            // Cache key
            $cacheKey = 'getroles_' . implode('_', $roleIds) . '_' . $page . '_' . $limit;

            if (RedisHelper::exists($cacheKey)) {
                $result = RedisHelper::get($cacheKey);
                if (is_array($result) && !empty($result)) {
                    return response()->json([
                        'data' => $result,
                        'total_count' => $total_count,
                        'total_pages' => $total_pages,
                        'current_page' => $page,
                        'cache_key' => $cacheKey,
                    ]);
                }
            }

            // Consulta con paginación
            $sqlPaginated = $sql . " LIMIT ? OFFSET ?";
            $stmt = $conn->prepare($sqlPaginated);
            $params = array_merge($roleIds, [$limit, $offset]);
            $stmt->execute($params);
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Almacenar resultado en Redis
            RedisHelper::set($cacheKey, $result, 3600); // 1 hora de vida útil

            // Retorno de resultado con información de paginación
            return response()->json([
                'data' => $result,
                'total_count' => $total_count,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'cache_key' => $cacheKey,
            ]);

        } catch (\Throwable $th) {
            // Captura error
            $capture = $th->getMessage();
            $msjerror = "Error de la BD: $capture";
            return response()->json(['error' => $msjerror], 500);
        } finally {
            // Cerrar la conexión
            $conn = null;
        }
    }

    // ENROL_T modalidad A Distancia
    public function getModalidadT($request)
    {
        // Parámetros de paginación
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;

        // Conexión a la base de datos
        $host = env('HOST_REPLICA');
        $dbname = env('DB_REPLICA');
        $port = env('PORT_REPLICA');
        $user = env('USER_REPLICA');
        $password = env('PASS_REPLICA');

        try {
            // Crear conexión a la DB
            $conn = new \PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Definición de la consulta principal
            $sql = "SELECT UL.\"USR_CORREO\"
                FROM \"INTEGRACION\".\"USUARIO_LMS\" UL
                INNER JOIN \"INTEGRACION\".\"USUARIO_LMS_ENROLL_T\" ULET
                ON UL.\"LMS_ID\" = ULET.userid
                INNER JOIN \"INTEGRACION\".\"V_FICHA_CARACTERIZACION_B\" VFCB
                ON ULET.\"FIC_ID\" = VFCB.\"FIC_ID\"
                WHERE VFCB.\"FIC_MOD_FORMACION\"='A'";

            // Consulta para contar el total de registros
            $countSql = "SELECT COUNT(*) FROM ($sql) AS total";
            $stmt = $conn->prepare($countSql);
            $stmt->execute();
            $total_count = $stmt->fetchColumn();

            // Cálculo de las páginas
            $total_pages = ceil($total_count / $limit);
            $offset = ($page - 1) * $limit;

            // Cache key
            $cacheKey = 'getModalidadT_' . $page . '_' . $limit;

            if (RedisHelper::exists($cacheKey)) {
                $result = RedisHelper::get($cacheKey);
                if (is_array($result) && !empty($result)) {
                    return response()->json([
                        'data' => $result,
                        'total_count' => $total_count,
                        'total_pages' => $total_pages,
                        'current_page' => $page,
                        'cache_key' => $cacheKey,
                    ]);
                }
            }

            // Consulta con paginación
            $sqlPaginated = $sql . " LIMIT :limit OFFSET :offset";
            $stmt = $conn->prepare($sqlPaginated);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Almacenar resultado en Redis
            RedisHelper::set($cacheKey, $result, 3600); // 1 hora de vida útil

            // Retorno de resultado con información de paginación
            return response()->json([
                'data' => $result,
                'total_count' => $total_count,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'cache_key' => $cacheKey,
            ]);

        } catch (\Throwable $th) {
            $capture = $th->getMessage();
            $msjerror = "Error de la BD: $capture";
            return response()->json(['error' => $msjerror], 500);
        } finally {
            // Cerrar la conexión
            $conn = null;
        }
    }

    // ENROL_C modalidad virtual
    public function getModalidadV($request)
    {
        // Parámetros de paginación
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;

        // Conexión a la base de datos
        $host = env('HOST_REPLICA');
        $dbname = env('DB_REPLICA');
        $port = env('PORT_REPLICA');
        $user = env('USER_REPLICA');
        $password = env('PASS_REPLICA');

        try {
            // Crear conexión a la DB
            $conn = new \PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Consulta principal
            $sql = "SELECT ul.\"USR_CORREO\"
                FROM \"INTEGRACION\".\"USUARIO_LMS\" ul
                INNER JOIN \"INTEGRACION\".\"USUARIO_LMS_ENROLL_C\" ulec ON ul.\"LMS_ID\" = ulec.userid
                INNER JOIN \"INTEGRACION\".\"V_FICHA_CARACTERIZACION_B\" vfcb ON ulec.\"FIC_ID\" = vfcb.\"FIC_ID\"
                UNION
                SELECT ul.\"USR_CORREO\"
                FROM \"INTEGRACION\".\"USUARIO_LMS\" ul
                INNER JOIN \"INTEGRACION\".\"USUARIO_LMS_ENROLL_T\" ulet ON ul.\"LMS_ID\" = ulet.userid
                INNER JOIN \"INTEGRACION\".\"V_FICHA_CARACTERIZACION_B\" vfcb ON ulet.\"FIC_ID\" = vfcb.\"FIC_ID\"";

            // Consulta para contar el total de registros
            $countSql = "SELECT COUNT(*) FROM ($sql) AS total";
            $stmt = $conn->prepare($countSql);
            $stmt->execute();
            $total_count = $stmt->fetchColumn();

            // Cálculo de las páginas
            $total_pages = ceil($total_count / $limit);
            $offset = ($page - 1) * $limit;

            // Cache key
            $cacheKey = 'getModalidadV_' . $page . '_' . $limit;

            if (RedisHelper::exists($cacheKey)) {
                $result = RedisHelper::get($cacheKey);
                if (is_array($result) && !empty($result)) {
                    return response()->json([
                        'data' => $result,
                        'total_count' => $total_count,
                        'total_pages' => $total_pages,
                        'current_page' => $page,
                        'cache_key' => $cacheKey,
                    ]);
                }
            }

            // Consulta con paginación
            $sqlPaginated = $sql . " LIMIT :limit OFFSET :offset";
            $stmt = $conn->prepare($sqlPaginated);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Almacenar resultado en Redis
            RedisHelper::set($cacheKey, $result, 3600); // 1 hora de vida útil

            return response()->json([
                'data' => $result,
                'total_count' => $total_count,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'cache_key' => $cacheKey,
            ]);
        } catch (\Throwable $th) {
            $capture = $th->getMessage();
            $msjerror = "Error de la BD: $capture";
            return response()->json(['error' => $msjerror], 500);
        } finally {
            // Cerrar la conexión
            $conn = null;
        }
    }

    public function getModalidadP($request)
    {
        // Parámetros de paginación
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;

        // Conexión a la base de datos
        $host = env('HOST_REPLICA');
        $dbname = env('DB_REPLICA');
        $port = env('PORT_REPLICA');
        $user = env('USER_REPLICA');
        $password = env('PASS_REPLICA');

        try {
            // Crear conexión a la DB
            $conn = new \PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Definición de la consulta principal
            $sql = "SELECT UL.\"USR_CORREO\"
                        FROM \"INTEGRACION\".\"USUARIO_LMS\" UL
                        INNER JOIN \"INTEGRACION\".\"USUARIO_LMS_ENROLL_P\" ULEP
                        ON UL.\"LMS_ID\" = ULEP.userid
                        INNER JOIN \"INTEGRACION\".\"V_FICHA_CARACTERIZACION_B\" VFCB
                        ON ULEP.\"FIC_ID\" = VFCB.\"FIC_ID\"
                        WHERE VFCB.\"FIC_MOD_FORMACION\"='A'";

            // Consulta para contar el total de registros
            $countSql = "SELECT COUNT(*) FROM ($sql) AS total";
            $stmt = $conn->prepare($countSql);
            $stmt->execute();
            $total_count = $stmt->fetchColumn();

            // Cálculo de las páginas
            $total_pages = ceil($total_count / $limit);
            $offset = ($page - 1) * $limit;

            // Cache key
            $cacheKey = 'getModalidadP_' . $page . '_' . $limit;

            if (RedisHelper::exists($cacheKey)) {
                $result = RedisHelper::get($cacheKey);
                if (is_array($result) && !empty($result)) {
                    return response()->json([
                        'data' => $result,
                        'total_count' => $total_count,
                        'total_pages' => $total_pages,
                        'current_page' => $page,
                        'cache_key' => $cacheKey,
                    ]);
                }
            }

            // Consulta con paginación
            $sqlPaginated = $sql . " LIMIT :limit OFFSET :offset";
            $stmt = $conn->prepare($sqlPaginated);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Almacenar resultado en Redis
            RedisHelper::set($cacheKey, $result, 3600); // 1 hora de vida útil

            // Retorno de resultado con información de paginación
            return response()->json([
                'data' => $result,
                'total_count' => $total_count,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'cache_key' => $cacheKey,
            ]);

        } catch (\Throwable $th) {
            $capture = $th->getMessage();
            $msjerror = "Error de la BD: $capture";
            return response()->json(['error' => $msjerror], 500);
        } finally {
            // Cerrar la conexión
            $conn = null;
        }
    }

//********************************************************************************************************************** */

    public function getAll($request)
    {
        // Parámetros de paginación
        
        $page = $request->page ?? 1;
        $limit = $request->limit ?? 20;
        
       // Conexión a la base de datos
       
        $host = $_ENV['HOST_REPLICA'];
        $dbname = $_ENV['DB_REPLICA'];
        $port = $_ENV['PORT_REPLICA'];
        $user = $_ENV['USER_REPLICA'];
        $password = $_ENV['PASS_REPLICA'];
             
        try {
            // Crear conexión a la DB
            $conn = new \PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
            $conn->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

            // Consulta principal
            $sql = "SELECT vib.\"NIS_FUN_INSTRUCTOR\" nis, vib.\"FIC_ID\", vfcb.\"PRF_DENOMINACION\",
                        vfcb.\"FIC_FCH_INICIALIZACION\", vfcb.\"FIC_FCH_FINALIZACION\", vfcb.\"FIC_MOD_FORMACION\",
                        nf.\"NFS_NOMBRE\", \"NFS_TIPO_FORMACION\", nf.\"NFS_ID\", vfb.\"PER_CORREO_E\",(vfb.\"PER_NOMBRE\" ||' '|| vfb.\"PER_PRIMER_APELLIDO\"||' '|| vfb.\"PER_SEGUNDO_APELLIDO\") AS \"NOMBRE_COMPLETO\", vfb.\"RGN_ID\",
                        vfb.\"RGN_NOMBRE\", 'I' as rol
                        FROM \"INTEGRACION\".\"V_INSTRUCTORXFICHA_B\" vib
                        INNER JOIN \"INTEGRACION\".\"V_FICHA_CARACTERIZACION_B\" vfcb ON vib.\"FIC_ID\" = vfcb.\"FIC_ID\"
                        INNER JOIN \"INTEGRACION\".\"V_PROGRAMA_FORMACION_B\" vpfb ON vfcb.\"PRF_ID\" = vpfb.\"PRF_ID\"
                        INNER JOIN \"INTEGRACION\".\"NIVEL_FORMACION\" nf ON nf.\"NFS_ID\" = vpfb.\"NFS_ID_OFRECIDO\"
                        INNER JOIN \"INTEGRACION\".\"V_FUNCIONARIO_B\" vfb ON vib.\"NIS_FUN_INSTRUCTOR\" = vfb.\"NIS\"

                        UNION ALL
                        SELECT vrab.\"NIS\" nis, vrab.\"FIC_ID\", vfcb.\"PRF_DENOMINACION\",
                        vfcb.\"FIC_FCH_INICIALIZACION\", vfcb.\"FIC_FCH_FINALIZACION\", vfcb.\"FIC_MOD_FORMACION\",
                        nf.\"NFS_NOMBRE\", \"NFS_TIPO_FORMACION\", nf.\"NFS_ID\", vpb.\"PER_CORREO_E\",(vpb.\"PER_NOMBRE\" ||' '|| vpb.\"PER_PRIMER_APELLIDO\"||' '|| vpb.\"PER_SEGUNDO_APELLIDO\") AS \"NOMBRE_COMPLETO\", r.\"RGN_ID\",
                        r.\"RGN_NOMBRE\", 'A' as rol
                        FROM \"INTEGRACION\".\"V_REGISTRO_ACADEMICO_B\" vrab
                        INNER JOIN \"INTEGRACION\".\"V_FICHA_CARACTERIZACION_B\" vfcb ON vrab.\"FIC_ID\" = vfcb.\"FIC_ID\"
                        INNER JOIN \"INTEGRACION\".\"V_PROGRAMA_FORMACION_B\" vpfb ON vfcb.\"PRF_ID\" = vpfb.\"PRF_ID\"
                        INNER JOIN \"INTEGRACION\".\"NIVEL_FORMACION\" nf ON nf.\"NFS_ID\" = vpfb.\"NFS_ID_OFRECIDO\"
                        INNER JOIN \"INTEGRACION\".\"V_PERSONA_B\" vpb ON vrab.\"NIS\" = vpb.\"NIS\"
                        INNER JOIN \"INTEGRACION\".\"REGIONAL\" r ON r.\"RGN_ID\" = vpfb.\"RGN_ID\"";

            // Consulta para contar el total de registros
            $countSql = "SELECT COUNT(*) FROM ($sql) AS total";
            $stmt = $conn->prepare($countSql);
            $stmt->execute();
            $total_count = $stmt->fetchColumn();

            // Cálculo de las páginas
            $total_pages = ceil($total_count / $limit);
            $offset = ($page - 1) * $limit;
             
            // Cache key
            $cacheKey = 'getAll_' . $page . '_' . $limit;

            if (RedisHelper::exists($cacheKey)) {
                $result = RedisHelper::get($cacheKey);
                if (is_array($result) && !empty($result)) {
                    return response()->json([
                        'data' => $result,
                        'total_count' => $total_count,
                        'total_pages' => $total_pages,
                        'current_page' => $page,
                        'cache_key' => $cacheKey,
                    ]);
                }
            }

            // Consulta con paginación
            $sqlPaginated = $sql . " LIMIT :limit OFFSET :offset";
            $stmt = $conn->prepare($sqlPaginated);
            $stmt->bindValue(':limit', $limit, \PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
            $stmt->execute();
            $result = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Almacenar resultado en Redis
            RedisHelper::set($cacheKey, $result, 3600); // 1 hora de vida útil

            // Almacenar resultado en Redis sin expiración (forever)
            //RedisHelper::set($cacheKey, $result);

            return response()->json([
                'data' => $result,
                'total_count' => $total_count,
                'total_pages' => $total_pages,
                'current_page' => $page,
                'cache_key' => $cacheKey,
            ]);
        } catch (\Throwable $th) {
            $capture = $th->getMessage();
            $msjerror = "Error de la BD: $capture";
            return response()->json(['error' => $msjerror], 500);
        } finally {
            // Cerrar la conexión
            $conn = null;
        }
    }


}

