<?php declare(strict_types=1);

// ini_set('implicit_flush', 1);

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;

date_default_timezone_set('America/Caracas');

return function (App $app) {
    $app->options('/{routes:.*}', function (Request $request, Response $response) {
        // CORS Pre-Flight OPTIONS Request Handler
        return $response;
    });

    // ROOT
    $app->get('/', function (Request $request, Response $response) {
        $array['api'] = 'ninesys 4';
        $array['Ver'] = '3.4.9 PLUS 01';

        $response->getBody()->write(json_encode($array));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** * PRUEBAS DE HISTÓRICO */

    /* $app->get('/h/backup/pagos', function (Request $request, Response $response) {
          $sql = "SELECT MAX(_id) + 1 id FROM ordenes";

          $localConnection = new LocalDB();
          $data = $localConnection->goQueryCopy($sql);
          $localConnection->disconnect();

          if (!$data[0]["id"]) {
              $data[0]["id"] = "1";
          }

          $input = str_pad($data[0]["id"], 3, "0", STR_PAD_LEFT);
          // $input = '33';
          // $nextId["crudo"] =  $data[0]["id"];
          $nextId["id"] = str_pad($input, 3, "0", STR_PAD_LEFT);

          $response->getBody()->write(json_encode($nextId));
          return $response
              ->withHeader('Content-Type', 'application/json')
              ->withStatus(200);
      }); */
    /** * FIN PRUEBAS DE HISTÓRICO */

    /** WhsatsApp */

    // GUARDAR DATOS DE LA CONFIGURACIÓN DEL SISTEMA
    // $app->get('/config', function (Request $request, Response $response) {
    $app->post('/config/select-empleados', function (Request $request, Response $response, $args) {
        $datos = $request->getParsedBody();

        /*  if ($datos["estado"] == true) {
           $estado = 1;
         } else {
           $estado = 0;
         } */

        // DETERMINAR QUE DEPARTAMENTO HACE QUE
        $departamento = $datos['departamento'];

        switch ($departamento) {
            case 'Estampado':
                $campo = 'sys_mostrar_rollo_en_empleado_estampado';
                break;
            case 'Corte':
                $campo = 'sys_mostrar_rollo_en_empleado_corte';
                break;
            case 'Costura':
                $campo = 'sys_mostrar_insumo_en_empleado_costura';
                break;
            case 'Limpieza':
                $campo = 'sys_mostrar_insumo_en_empleado_limpieza';
                break;
            case 'Revisión':
                $campo = 'sys_mostrar_insumo_en_empleado_revision';
                break;
            default:
                $campo = 'Unknown';
                break;
        }

        if ($campo != 'Unknown') {
            $localConnection = new LocalDB();
            $sql = 'UPDATE config SET ' . $campo . ' = ' . $datos['estado'] . ' WHERE _id = 1';
            $object['sql'] = $sql;
            $object['response'] = $localConnection->goQuery($sql);
            $localConnection->disconnect();
        } else {
            $object['response'] = 'No existe el departamento ' . $datos['departamento'];
        }

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER DATOS DE LA CONFIGURACIÓN DEL SISTEMA
    $app->get('/config', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $sql = 'SELECT * FROM config';
        $data = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($data[0], JSON_NUMERIC_CHECK));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/send-message', function (Request $request, Response $response, $args) {
        $datosAcceso = $request->getParsedBody();

        $localConnection = new LocalDB();
        $msgApi = new WhatsAppAPIClient();

        $sql = "SELECT _id, username, departamento, nombre, email FROM empleados WHERE username = '" . $datosAcceso['username'] . "' AND password = '" . $datosAcceso['password'] . "' AND activo = 1 AND acceso = 1";
        $object['sql'] = $sql;
        $resp = $localConnection->goQuery($sql);

        if (empty($resp)) {
            $object['access'] = false;
            $object['user_data'] = null;
        } else {
            $object['access'] = true;
            $object['user_data'] = $resp;
        }

        $localConnection->disconnect();

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

        $response->getBody()->write(json_encode($object));

        return $response;
    });

    /** * Login */
    $app->post('/login', function (Request $request, Response $response, $args) {
        $datosAcceso = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = 'SELECT * FROM config';
        $conf = $localConnection->goQuery($sql);
        $object['datasys'] = $conf[0];

        $sql = "SELECT _id, activo, username, departamento, nombre, email, comision, acceso, (SELECT app_key FROM config WHERE _id = 1) as token FROM empleados WHERE username = '" . $datosAcceso['username'] . "' AND password = '" . $datosAcceso['password'] . "' AND activo = 1";

        $data = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        if (empty($data)) {
            $data[0] = false;
            $access = false;
        } else {
            $access = true;
        }

        $object['data']['access'] = $access;
        $object['data']['res'] = $data[0];

        if ($access) {
            $object['data']['id_empleado'] = $data[0]['_id'];
            $object['data']['departamento'] = $data[0]['departamento'];
            $object['data']['nombre'] = $data[0]['nombre'];
            $object['data']['username'] = $data[0]['username'];
            $object['data']['email'] = $data[0]['email'];
            $object['data']['comision'] = $data[0]['comision'];
            $object['data']['acceso'] = intval($data[0]['acceso']);
            $object['data']['app_key'] = $data[0]['token'];
        } else {
            $object['data']['id_empleado'] = null;
            $object['data']['departamento'] = null;
            $object['data']['nombre'] = null;
            $object['data']['username'] = null;
            $object['data']['email'] = null;
            $object['data']['comision'] = 0;
            $object['data']['acceso'] = 0;
            $object['data']['token'] = 0;
        }

        $object['dat'] = $object['data']['res'];

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

        $response->getBody()->write(json_encode($object));

        return $response;
    });

    /** * Login mensajes */
    $app->post('/verify-credentials', function (Request $request, Response $response, $args) {
        $datosAcceso = $request->getParsedBody();

        $localConnection = new LocalDB();

        $sql = "SELECT _id, username, departamento, nombre, email FROM empleados WHERE username = '" . $datosAcceso['username'] . "' AND password = '" . $datosAcceso['password'] . "' AND activo = 1 AND acceso = 1";
        $object['sql'] = $sql;
        $resp = $localConnection->goQuery($sql);

        if (empty($resp)) {
            $object['access'] = false;
            $object['user_data'] = null;
        } else {
            $object['access'] = true;
            $object['user_data'] = $resp;
        }

        $localConnection->disconnect();

        $response = $response
            ->withHeader('Access-Control-Allow-Origin', '*')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

        return $response;
    });

    /** FIN LOGIN */

    /** * GENERAL */
    $app->get('/next-id-order', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $sql = 'SELECT MAX(_id) + 1 id FROM ordenes';
        $data = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        if (!$data[0]['id']) {
            $data[0]['id'] = '1';
        }

        $input = str_pad($data[0]['id'], 3, '0', STR_PAD_LEFT);
        $nextId['id'] = str_pad($input, 3, '0', STR_PAD_LEFT);

        $response->getBody()->write(json_encode($nextId));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** FIN GENRAL */

    /** * TABLAS */
    // REPORTE DE PRODUCCIÓN SEMANAL
    $app->get('/ordenes-reporte-semanal-produccion/{fecha}', function (Request $request, Response $response, array $args) {
        $fechaSegundos = strtotime($args['fecha']);
        $week = date('W', $fechaSegundos);
        $object['week'] = $week;
        $localConnection = new LocalDB();

        // ORDENES DE PRODUCIDAS EN LA SEMANA
        $sql = "SELECT
        a._id id_orden,
        a.cliente_nombre cliente,    
        DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') AS fecha_inicio,
        DATE_FORMAT(a.fecha_entrega, '%d/%m/%Y') AS fecha_entrega,
        a.status estatus
        FROM
        ordenes a
        WHERE
        WEEK(a.moment) = " . $week;
        $object['items'] = $localConnection->goQuery($sql);

        // PROPDUCTOS ASOICIADOS A LAS ORDENES DE LA SEMANA
        $sql = 'SELECT
        a._id id_ordenes_productos,
        a.id_orden,
        a.id_woo,
        a.name,
        a.cantidad,
        a.talla,
        a.corte,
        a.tela
        FROM
        ordenes_productos a
        WHERE
        a.id_woo != 11 AND 
        a.id_woo != 12 AND 
        a.id_woo != 13 AND 
        a.id_woo != 14 AND 
        a.id_woo != 15 AND 
        a.id_woo != 16 AND 
        a.id_woo != 112 AND 
        a.id_woo != 113 AND 
        a.id_woo != 168 AND 
        a.id_woo != 169 AND 
        WEEK(a.moment) = ' . $week . ' 
        ORDER BY a.name ASC, a.corte ASC, a.talla ASC, a.tela ASC, a.id_orden ASC;';
        $object['items_productos'] = $localConnection->goQuery($sql);

        // INSERTAR PRODUCTOS EN items

        foreach ($object['items'] as $key => $orden) {
            foreach ($object['items_productos'] as $producto) {
                if (!isset($object['items'][$key]['productos'])) {
                    $object['items'][$key]['productos'] = [];
                }

                if ($producto['id_orden'] === $orden['id_orden']) {
                    $object['items'][$key]['productos'][] = $producto;
                }
            }
        }

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // REPORTE SEMANAL DE ORDENES
    $app->get('/ordenes-reporte-semanal/{fecha}', function (Request $request, Response $response, array $args) {
        $fechaSegundos = strtotime($args['fecha']);
        $week = date('W', $fechaSegundos);
        $object['week'] = $week;
        $localConnection = new LocalDB();

        $sql = 'SELECT
        a._id orden,
        a.cliente_nombre cliente,
        a.pago_total total,
        a.pago_abono abono,
        a.pago_descuento descuento,
        b.nombre empleado,
        (a.pago_total - a.pago_descuento) - a.pago_abono AS total_pendiente
        FROM
        ordenes a
        JOIN empleados b ON a.responsable = b._id
        WHERE
        WEEK(a.moment) = ' . $week;
        $object['items'] = $localConnection->goQuery($sql);

        $sql = 'SELECT
        SUM(pago_abono) total_semana
        FROM ordenes 
        WHERE
        WEEK(moment) = ' . $week . ' ORDER BY _id ASC';
        $object['total_week'] = $localConnection->goQuery($sql);

        if (is_null($object['total_week'][0]['total_semana'])) {
            $object['total_week'][0]['total_semana'] = '0';
        }

        $sql = 'SELECT
        (SUM(pago_total) - SUM(pago_descuento)) - SUM(pago_abono) total_credito
        FROM ordenes 
        WHERE
        WEEK(moment) = ' . $week . ' ORDER BY _id ASC';
        $object['total_credito'] = $localConnection->goQuery($sql);

        if (is_null($object['total_credito'][0]['total_credito'])) {
            $object['total_credito'][0]['total_credito'] = '0';
        }

        $sql = 'SELECT
        SUM(pago_descuento) total_descuentos
        FROM ordenes 
        WHERE
        WEEK(moment) = ' . $week . ' ORDER BY _id ASC';
        $object['total_descuentos'] = $localConnection->goQuery($sql);

        if (is_null($object['total_descuentos'][0]['total_descuentos'])) {
            $object['total_descuentos'][0]['total_descuentos'] = '0';
        }

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER PRESUPUESTOS GUARDADOS
    $app->get('/presupuestos/guardados', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $sql = 'SELECT a._id, a.form, a.tipo, b._id AS id_empleadodo, b.nombre AS empleado 
          FROM ordenes_tmp a 
          JOIN empleados b ON a.id_empleado = b._id';

        $object['items'] = $localConnection->goQuery($sql);

        foreach ($object['items'] as $key => $item) {
            $item[$key]['form'] = json_decode($item['form']);
        }

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER ORDENES GUARDADAS
    $app->get('/ordenes/guardadas', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $sql = 'SELECT a._id, a.form, a.tipo, b._id AS id_empleadodo, b.nombre AS empleado 
          FROM ordenes_tmp a 
          JOIN empleados b ON a.id_empleado = b._id';

        $object['items'] = $localConnection->goQuery($sql);

        foreach ($object['items'] as $key => $item) {
            // $item[$key]['form'] = json_decode($item['form']);
            if (is_array($item) && isset($item['form'])) {
                $item['form'] = json_decode($item['form']);
            }
        }

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/ordenes/guardadas-old', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        // $sql = "SELECT a._id, a.form, b._id id_empleadodo, b.nombre empleado FROM ordenes_tmp a JOIN empleados b ON a.id_empleado = b._id";
        $sql = 'SELECT a._id, a.form, b._id id_empleadodo, b.nombre empleado FROM ordenes_tmp a JOIN empleados b ON a.id_empleado = b._id';
        // $object['sql'] = $sql;
        $object['items'] = $localConnection->goQuery($sql);

        foreach ($object['items'] as $key => $item) {
            // $item[$key]['form'] = json_decode(addcslashes($item['form'])); // con error
            $item[$key]['form'] = json_decode($item['form']);
            // $item[$key]['form'] = json_encode(json_decode($item['form']));
            // $items = stripslashes(trim($string));
        }
        /*$string=implode("",explode("\\", json_encode($object['items']) ));
            $items = stripslashes(trim($string));
            $items = stripslashes(trim($items));*/

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // GUARDAR BORRADOR DEL EMPLEADO
    $app->post('/ordenes/borrador', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        // Verificar si ya existe un registro para la orden
        $sql = 'SELECT _id FROM ordenes_borrador_empleado WHERE id_orden = ' . $data['id_orden'] . ' AND id_empleado = ' . $data['id_empleado'];
        $resp = $localConnection->goQuery($sql);

        if (empty($resp)) {
            $sql = 'INSERT INTO ordenes_borrador_empleado (`id_orden`, `id_empleado`, `borrador`) VALUES (' . $data['id_orden'] . ', ' . $data['id_empleado'] . ", '" . addslashes($data['borrador']) . "');";
        } else {
            $sql = 'UPDATE ordenes_borrador_empleado SET id_orden = ' . $data['id_orden'] . ', id_empleado = ' . $data['id_empleado'] . ", borrador = '" . addslashes($data['borrador']) . "' WHERE id_orden = " . $data['id_orden'] . ' AND id_empleado = ' . $data['id_empleado'];
        }
        $object['sql'] = $sql;
        $resp = $localConnection->goQuery($sql);
        $object['resp'] = $localConnection->disconnect($sql);

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // ELIMINAR ORDENES GUARDADAS
    $app->post('/ordenes/guardadas/eliminar', function (Request $request, Response $response) {
        $localConnection = new LocalDB();
        $data = $request->getParsedBody();
        $sql = 'DELETE FROM ordenes_tmp WHERE _id =  ' . $data['id'];
        $object['response_delete'] = json_encode($localConnection->goQuery($sql));
        $object['sql_delete'] = $sql;

        $sql = 'SELECT a._id, a.form, b._id id_empleado, b.nombre empleado FROM ordenes_tmp a JOIN empleados b ON a.id_empleado = b._id';
        $object['items'] = $localConnection->goQuery($sql);

        $object['response'] = json_encode($localConnection->goQuery($sql));
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // GUARDAR ORDEN PARA REPTMARLA LUEGO
    $app->post('/orden/guardar', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        $localConnection = new LocalDB();

        $sql = "INSERT INTO ordenes_tmp (form, id_empleado, tipo) VALUES ('" . $data['form'] . "', " . $data['id_empleado'] . ", '" . $data['tipo'] . "')";
        $object['sql_insert'] = $sql;
        $localConnection->goQuery($sql);

        $sql = 'SELECT a._id, a.form, b._id id_empleado, b.nombre empleado FROM ordenes_tmp a JOIN empleados b ON a.id_empleado = b._id';
        $object['items'] = $localConnection->goQuery($sql);
        $object['sql'] = $sql;

        $string = implode('', explode('\\', json_encode($object['items'])));
        $items = stripslashes(trim($string));
        $items = stripslashes(trim($items));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($items));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // ORDENES ACTIVAS
    $app->get('/table/ordenes-activas/{id_empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $sql = 'SELECT departamento FROM empleados WHERE _id = ' . $args['id_empleado'];
        $departamento = $localConnection->goQuery($sql)[0]['departamento'];

        if ($departamento === 'Administración') {
            $sql = "SELECT
                ord.responsable,
                ord._id orden,
                ord._id id_father,
                ord._id acc,
                ord.cliente_nombre,
                cus.phone,
                cus.email,
                ord.fecha_inicio,
                ord.fecha_entrega,
                ord.observaciones obs,
                ord.status estatus
            FROM
                ordenes ord
            JOIN customers cus ON ord.id_wp = cus._id
            WHERE
                (
                ord.status
                    = 'activa' OR
                ord.status
                    = 'En espera' OR
                ord.status
                    = 'terminada' OR
                ord.status
                    = 'pausada'
            )
            ORDER BY
                ord._id
            DESC;";
        } else {
            $sql = "SELECT
                ord.responsable,
                ord._id orden,
                ord._id id_father,
                ord._id acc,
                ord.cliente_nombre,
                cus.phone,
                cus.email,
                ord.fecha_inicio,
                ord.fecha_entrega,
                ord.observaciones obs,
                ord.status estaus
            FROM
                ordenes ord
            JOIN customers cus ON ord.id_wp = cus._id
            WHERE
                ord.responsable = '" . $args['id_empleado'] . "' AND(
                ord.status
                    = 'activa' OR
                ord.status
                    = 'En espera' OR
                ord.status
                    = 'terminada' OR
                ord.status
                    = 'pausada'
            ) AND ord.pago_comision = 'pendiente'
            ORDER BY
                ord._id
            DESC;";
        }

        $object['sql'] = $sql;

        // Cabeceras de la tabla
        $object['fields'][0]['key'] = 'orden';
        $object['fields'][0]['label'] = 'Orden';
        $object['fields'][0]['sortable'] = true;

        $object['fields'][1]['key'] = 'estatus';
        $object['fields'][1]['label'] = 'Estatus';
        $object['fields'][1]['sortable'] = true;

        $object['fields'][2]['key'] = 'fecha_inicio';
        $object['fields'][2]['label'] = 'Inicio';
        $object['fields'][2]['sortable'] = true;

        $object['fields'][3]['key'] = 'fecha_entrega';
        $object['fields'][3]['label'] = 'Entrega';
        $object['fields'][3]['sortable'] = true;

        $object['fields'][4]['key'] = 'cliente_nombre';
        $object['fields'][4]['label'] = 'Cliente';
        $object['fields'][4]['sortable'] = true;

        $object['fields'][5]['key'] = 'id_father';
        $object['fields'][5]['label'] = 'Vinculadas';
        $object['fields'][5]['sortable'] = false;

        $object['fields'][6]['key'] = 'acc';
        $object['fields'][6]['label'] = 'Acciones';
        $object['fields'][6]['sortable'] = false;

        $object['items'] = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // TODAS LAS ORDENES
    $app->get('/table/ordenes-todas', function (Request $request, Response $response, array $args) {
        /*$sql = "SELECT
            _id AS orden,
            DATE_FORMAT(moment, '%d/%m/%Y') AS fecha,
            cliente_nombre AS cliente,
            pago_total AS monto,
            pago_abono abono,
            (pago_total - pago_abono) AS monto_pendiente,
            status estatus
            FROM
            ordenes
            WHERE status != 'cancelada'
            ORDER BY _id DESC;";*/

        $sql = "SELECT
        a._id AS orden,
        DATE_FORMAT(moment, '%d/%m/%Y') AS fecha,
        a.cliente_nombre AS cliente,
        a.pago_total AS monto,
        a.pago_abono abono,
        (SELECT cus.phone FROM customers cus WHERE cus._id = a.id_wp) phone,
        (
         SELECT
         d.pago_total - SUM(c.abono) - SUM(c.descuento) AS total_deuda
         FROM
         abonos c
         JOIN ordenes d ON
         c.id_orden = d._id
         WHERE
         c.id_orden = a._id
         ) AS monto_pendiente,
        a.status estatus
        FROM
        ordenes AS a
        WHERE a.status != 'cancelada'
        ORDER BY a._id DESC;";

        $localConnection = new LocalDB();
        $items = $localConnection->goQuery($sql);

        $object['items'] = $items;

        // Cabeceras de la tabla
        $object['fields'][0]['key'] = 'orden';
        $object['fields'][0]['label'] = 'Orden';
        $object['fields'][0]['sortable'] = true;

        $object['fields'][1]['key'] = 'fecha';
        $object['fields'][1]['label'] = 'Fecha';
        $object['fields'][1]['sortable'] = true;

        $object['fields'][2]['key'] = 'cliente';
        $object['fields'][2]['label'] = 'Cliente';
        $object['fields'][2]['sortable'] = true;

        $object['fields'][3]['key'] = 'monto';
        $object['fields'][3]['label'] = 'Monto';
        $object['fields'][3]['sortable'] = true;

        $object['fields'][3]['key'] = 'acc';
        $object['fields'][3]['label'] = 'Acciones';
        $object['fields'][3]['sortable'] = false;

        // $object['items'] = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // ORDENES CON DEUDAA
    $app->get('/table/ordenes-con-deuda', function (Request $request, Response $response, array $args) {
        $sql = "SELECT
        _id AS orden,
        DATE_FORMAT(moment, '%d/%m/%Y') AS fecha,
        cliente_nombre AS cliente,
        pago_total AS monto
        FROM
        ordenes       
        ORDER BY _id DESC;";

        $sql = "SELECT
        a._id orden,
        a.responsable,
        a._id orden,
        a._id id_father,
        a._id acc,
        a.cliente_nombre cliente,
        a.fecha_inicio,
        a.fecha_entrega,
        a.observaciones obs,
        a.status estatus,
        a.pago_total AS monto,
        DATE_FORMAT(a.moment, '%d/%m/%Y') AS fecha,
        (
         SELECT
         d.pago_total - SUM(c.abono) - SUM(c.descuento) AS total_deuda
         FROM
         abonos c
         JOIN ordenes d ON
         c.id_orden = d._id
         WHERE
         c.id_orden = a._id
         ) AS total_deuda 
        FROM
        ordenes AS a
        WHERE
        a.status!= 'cancelada' AND 
        (
         SELECT
         d.pago_total - SUM(c.abono) - SUM(c.descuento) AS total_deuda
         FROM
         abonos c
         JOIN ordenes d ON
         c.id_orden = d._id
         WHERE
         c.id_orden = a._id) > 0
        ORDER BY
        _id
        DESC
        ";
        $localConnection = new LocalDB();
        $items = $localConnection->goQuery($sql);

        $object['items'] = $items;

        // Cabeceras de la tabla
        $object['fields'][0]['key'] = 'orden';
        $object['fields'][0]['label'] = 'Orden';
        $object['fields'][0]['sortable'] = true;

        $object['fields'][1]['key'] = 'fecha';
        $object['fields'][1]['label'] = 'Fecha';
        $object['fields'][1]['sortable'] = true;

        $object['fields'][2]['key'] = 'cliente';
        $object['fields'][2]['label'] = 'Cliente';
        $object['fields'][2]['sortable'] = true;

        $object['fields'][3]['key'] = 'monto';
        $object['fields'][3]['label'] = 'Monto';
        $object['fields'][3]['sortable'] = true;

        $object['fields'][3]['key'] = 'acc';
        $object['fields'][3]['label'] = 'Acciones';
        $object['fields'][3]['sortable'] = false;

        // $object['items'] = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /** FIN TABLAS */

    /** * TELAS */
    $app->get('/telas', function (Request $request, Response $response) {
        $localConnection = new LocalDB();
        $sql = 'SELECT * FROM catalogo_telas ORDER BY tela';
        $object['data'] = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/telas', function (Request $request, Response $response) {
        $miTela = $request->getParsedBody();
        $object['miTela'] = $miTela;

        $miTela = $request->getParsedBody();

        // Crear estructura de valores para insertar nuevo cliente
        $values = '(';
        $values .= "'" . $miTela['tela'] . "')";

        $sql = 'INSERT INTO catalogo_telas (`tela`) VALUES ' . $values . ';';
        $sql .= 'SELECT * FROM catalogo_telas ORDER BY tela';

        $localConnection = new LocalDB();
        $object['response'] = json_encode($localConnection->goQuery($sql));
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/telas/{_id}/{tela}', function (Request $request, Response $response, array $args) {
        // $miTela = $request->getParsedBody();
        $localConnection = new LocalDB();
        $values = "tela='" . $args['tela'] . "'";
        $sql = 'UPDATE catalogo_telas SET ' . $values . ' WHERE _id = ' . $args['_id'] . ';';
        $sql .= 'SELECT * FROM catalogo_telas ORDER BY tela';
        $object['sql'] = $sql;
        $object['response'] = json_encode($localConnection->goQuery($sql));
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/telas/eliminar', function (Request $request, Response $response) {
        $localConnection = new LocalDB();
        $miEmpleado = $request->getParsedBody();
        $object['miEmpleado'] = $miEmpleado;
        $sql = 'DELETE FROM catalogo_telas WHERE _id =  ' . $miEmpleado['id'];
        $object['sql'] = $sql;

        $object['response'] = json_encode($localConnection->goQuery($sql));
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /** FIN TELAS */

    /** RETIROS */

    // REPORTE GENERAL DE PAGOS Y ABONOS
    $app->get('/reporte-de-pagos[/{inicio}/{fin}/{id_vendedor}]', function (Request $request, Response $response, array $args) {
        /** FONDO */
        $localConnection = new LocalDB();
        $inicio = isset($args['inicio']) ? $args['inicio'] : null;
        $fin = isset($args['fin']) ? $args['fin'] : null;
        $vendedor = isset($args['id_vendedor']) ? $args['id_vendedor'] : null;

        /* if (isset($args["id_vendedor"])) {
                $vendedor = $args["id_vendedor"];
            } else {
                $object["vendedor"] = $args["id_vendedor"];
                $vendedor = null;
            } */

        if (!is_null($vendedor)) {
            if ($vendedor == '0') {
                $searchVendedor = '';
            } else {
                $searchVendedor = ' AND ord.responsable = ' . $vendedor . ' ';
            }
        } else {
            $searchVendedor = '';
        }

        $object['searchVendedor'] = $searchVendedor;

        if (is_null($inicio) || is_null($fin)) {
            $sql = "SELECT
                met._id,
                ord._id orden,
                ord.responsable id_empleado,
                emp.nombre empleado,
                met.metodo_pago,
                met.monto,
                met.detalle,
                met.tasa,
                met.moneda,
                DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha,
                DATE_FORMAT(met.moment, '%h:%i %p') AS hora
            FROM
                metodos_de_pago met
            JOIN ordenes ord ON met.id_orden = ord._id 
            JOIN empleados emp ON emp._id = ord.responsable
            WHERE
                YEAR(met.moment) = YEAR(CURDATE())
                AND MONTH(met.moment) = MONTH(CURDATE()) 
                " . $searchVendedor . '
            ORDER BY
                met.id_orden DESC, met.moment ASC;
            ';
        } else {
            $sql = "SELECT
                met._id,
                ord._id orden,
                ord.responsable id_empleado,
                emp.nombre empleado,
                met.metodo_pago,
                met.monto,
                met.detalle,
                met.tasa,
                met.moneda,
                DATE_FORMAT(met.moment, '%d/%m/%Y') AS fecha,
                DATE_FORMAT(met.moment, '%h:%i %p') AS hora
            FROM
                metodos_de_pago met
            JOIN ordenes ord ON met.id_orden = ord._id 
            JOIN empleados emp ON emp._id = ord.responsable
            WHERE
                DATE(met.moment) BETWEEN '" . $inicio . "' AND '" . $fin . "' 
                " . $searchVendedor . '
                ORDER BY
                met.id_orden DESC, met.moment ASC;';
        }

        $object['sql_pagos'] = $sql;

        $object['pagos'] = $localConnection->goQuery($sql);

        // Buscar todos los empleados que sean vendedres o administradores
        $sqlv = "SELECT _id value, nombre text FROM empleados WHERE departamento = 'Comercialización' OR departamento = 'Administración' AND activo = 1;";
        $object['vendedores'] = $localConnection->goQuery($sqlv);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Datos para efectuar el cietre de caja
    $app->get('/cierre-de-caja', function (Request $request, Response $response, array $args) {
        /** FONDO */
        $localConnection = new LocalDB();
        $sql = 'SELECT dolares, pesos, bolivares FROM caja_fondos ORDER BY _id DESC LIMIT 1';
        $fondo = $localConnection->goQuery($sql);
        $object['data']['fondo'] = $fondo;

        if (empty($fondo)) {
            $fondo[0]['dolares'] = 0;
            $fondo[0]['pesos'] = 0;
            $fondo[0]['bolivares'] = 0;
        }

        // DÓLARES EN CAJA,
        $sql = 'SELECT (SUM(monto) + ' . $fondo[0]['dolares'] . ') monto, moneda, tasa, FORMAT(((SUM(monto) / tasa)) + ' . $fondo[0]['dolares'] . ", 'C2') dolares FROM caja WHERE moneda= 'Dólares'";
        $object['data']['caja'] = $localConnection->goQuery($sql);

        // PESOS EN CAJA,
        $sql = 'SELECT (SUM(monto) + ' . $fondo[0]['pesos'] . ') monto, moneda, tasa, FORMAT((SUM(monto) + ' . $fondo[0]['pesos'] . ") / tasa, 'C2') dolares FROM caja WHERE moneda= 'Pesos'";
        array_push($object['data']['caja'], $localConnection->goQuery($sql)[0]);

        // BOLIVARES     EN CAJA,
        $sql = 'SELECT (SUM(monto) + ' . $fondo[0]['bolivares'] . ') monto, moneda, tasa, FORMAT((SUM(monto) + ' . $fondo[0]['bolivares'] . ") / tasa, 'C2') dolares FROM caja WHERE moneda= 'Bolívares'";
        array_push($object['data']['caja'], $localConnection->goQuery($sql)[0]);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Guardar Cierre de caja
    $app->post('/cierre-de-caja', function (Request $request, Response $response, $args) {
        $datosCierre = $request->getParsedBody();
        $localConnection = new LocalDB();

        // Guardamos el cierre
        $sql = ' INSERT INTO caja_cierres (dolares, pesos, bolivares, id_empleado) VALUES (' . $datosCierre['cierreDolaresEfectivo'] . ', ' . $datosCierre['cierrePesosEfectivo'] . ', ' . $datosCierre['cierreBolivaresEfectivo'] . ', ' . $datosCierre['id_empleado'] . ');';
        $sql .= 'TRUNCATE caja;';
        $sql .= 'TRUNCATE caja_fondos;';
        $sql .= 'INSERT INTO caja_fondos (dolares, pesos, bolivares) VALUES (' . $datosCierre['fondoDolares'] . ', ' . $datosCierre['fondoPesos'] . ', ' . $datosCierre['fondoBolivares'] . ')';

        $object['response'] = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode(str_replace("\r", '', $object)));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Reporte de caja
    $app->get('/reporte-de-caja/{inicio}/{fin}/{id_vendedor}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        if ($args['inicio'] === $args['fin']) {
            $where = "a.moment LIKE '" . $args['inicio'] . "%';";
        } else {
            $where = "a.moment BETWEEN '" . $args['inicio'] . "' AND '" . $args['fin'] . "'";
        }

        $where .= ' AND o.responsable = ' . $args['id_vendedor'] . ';';

        /** EFECTIVO */

        // Dolares
        // $sql = "SELECT SUM(`monto`) monto, 'Dólares' moneda, `tasa`, `metodo_pago` tipo, SUM(`monto`) dolares FROM `metodos_de_pago` WHERE metodo_pago = 'Efectivo' AND `moneda` = 'Dólares' AND " . $where;

        $sql = "SELECT
            SUM(a.monto) monto,
            'Dólares' moneda,
            a.tasa,
            a.metodo_pago tipo,
            SUM(a.monto) dolares,
            o.responsable vendedor
        FROM
            metodos_de_pago AS a
        JOIN ordenes AS o 
            ON a.id_orden = o._id
        WHERE o.responsable = 1 AND 
            a.metodo_pago = 'Efectivo' AND a.moneda = 'Dólares' AND " . $where;
        $object['data']['efectivo'] = $localConnection->goQuery($sql);

        // Pesos
        $sql = "SELECT 
            SUM(a.monto) monto, 
            'Pesos' moneda, 
            a.tasa, 
            a.metodo_pago tipo, 
            SUM(ROUND(a.monto / a.tasa, 2)) AS dolares 
        FROM metodos_de_pago AS a
        JOIN ordenes AS o 
        ON a.id_orden = o._id       
        WHERE a.metodo_pago = 'Efectivo' AND a.moneda = 'Pesos' AND " . $where;
        // $object["sql"] = $sql;

        array_push($object['data']['efectivo'], $localConnection->goQuery($sql)[0]);

        // Bolívares
        $sql = "SELECT 
            SUM(a.monto) monto, 
             'Bolívares' moneda, 
            a.tasa, 
            a.metodo_pago tipo, 
            SUM(ROUND(a.monto / a.tasa, 2)) AS dolares 
        FROM metodos_de_pago AS a
        JOIN ordenes AS o 
        ON a.id_orden = o._id
        WHERE a.metodo_pago = 'Efectivo' AND a.moneda = 'Bolívares' AND " . $where;

        array_push($object['data']['efectivo'], $localConnection->goQuery($sql)[0]);

        /** MONEDA DIGITAL */

        // ZELLE

        $sql = "SELECT 
             SUM(a.monto) monto, 
             a.tasa, 
             SUM(ROUND(a.monto / a.tasa, 2)) AS dolares, 
             a.moneda, 
             'Zelle' metodo_pago, 
             a.tipo_de_pago 
             FROM metodos_de_pago AS a 
             JOIN ordenes AS o 
             ON a.id_orden = o._id
             WHERE a.metodo_pago = 'Zelle' AND " . $where;
        $object['data']['digital'] = $localConnection->goQuery($sql);

        // PAGOMOVIL (bOLIVARES)
        $sql = "SELECT 
            SUM(a.monto) monto, 
            a.tasa, 
            SUM(ROUND(a.monto / a.tasa, 2)) AS dolares, 
            a.moneda, 
            'Pagomovil' metodo_pago, 
            a.tipo_de_pago 
            FROM metodos_de_pago AS a 
            JOIN ordenes AS o 
            ON a.id_orden = o._id
            WHERE a.metodo_pago = 'Pagomovil' AND " . $where;

        array_push($object['data']['digital'], $localConnection->goQuery($sql)[0]);

        // PUNTO (BOLIVARES)
        $sql = "SELECT 
            SUM(a.monto) monto, 
            a.tasa, 
            SUM(ROUND(a.monto / a.tasa, 2)) AS dolares, 
            a.moneda, 
            'Punto' metodo_pago, 
            a.tipo_de_pago 
            FROM metodos_de_pago AS a 
            JOIN ordenes AS  o 
            ON a.id_orden = o._id
            WHERE a.metodo_pago = 'Punto' AND " . $where;

        array_push($object['data']['digital'], $localConnection->goQuery($sql)[0]);

        // TRANSFERENCIA (BOLIVARES)
        $sql = "SELECT 
            SUM(a.monto) monto, 
            a.tasa, 
            SUM(ROUND(a.monto / a.tasa, 2)) AS dolares, 
            a.moneda, 
            'Transferencia' metodo_pago, 
            a.tipo_de_pago 
            FROM metodos_de_pago AS a 
            JOIN ordenes AS o 
            ON a.id_orden = o._id
            WHERE a.metodo_pago = 'Punto' AND " . $where;

        array_push($object['data']['digital'], $localConnection->goQuery($sql)[0]);

        /** RETIROS */
        $sql = 'SELECT 
            a.monto, 
            a.moneda, 
            a.tasa, 
            SUM(ROUND(a.monto / tasa, 2)) AS dolares, 
            a.detalle_retiro, 
            b.nombre 
            FROM retiros AS a 
            JOIN ordenes AS o ON o._id = a.id_empleado
            JOIN empleados b ON b._id = o.responsable 
            WHERE ' . $where;

        // $object["data"]["retiros"] = $sql;
        $object['data']['retiros'] = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Guardar nuevo retiro
    $app->post('/retiro', function (Request $request, Response $response) {
        $arr = $request->getParsedBody();
        $localConnection = new LocalDB();
        // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
        $sql = '';

        if (intval($arr['montoDolaresEfectivo']) > 0) {
            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '" . $arr['detalle'] . "', '1');";
        }

        if (intval($arr['montoDolaresZelle']) > 0) {
            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '" . $arr['detalle'] . "', '1');";
        }

        if (intval($arr['montoDolaresPanama']) > 0) {
            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '" . $arr['detalle'] . "', '1');";
        }

        if (intval($arr['montoPesosEfectivo']) > 0) {
            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_peso'] . "');";
        }

        if (intval($arr['montoPesosTransferencia']) > 0) {
            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_peso'] . "');";
        }

        if (intval($arr['montoBolivaresEfectivo']) > 0) {
            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";
        }

        if (intval($arr['montoBolivaresPunto']) > 0) {
            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";
        }

        if (intval($arr['montoBolivaresPagomovil']) > 0) {
            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";
        }

        if (intval($arr['montoBolivaresTransferencia']) > 0) {
            $sql .= "INSERT INTO retiros (id_empleado, moneda, metodo_pago, monto, detalle_retiro, tasa) VALUES ('" . $arr['id_empleado'] . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";
        }

        $data = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Guardar nuevo abono
    $app->post('/otro-abono', function (Request $request, Response $response) {
        $arr = $request->getParsedBody();
        $localConnection = new LocalDB();
        // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
        $sql = '';

        if (intval($arr['montoDolaresEfectivo']) > 0) {
            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr['tipoAbono'] . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '" . $arr['detalle'] . "', '1');";
            $sql .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $arr['montoDolaresEfectivo'] . "', 'Dólares', 1, 'abono', '" . $arr['id_empleado'] . "');";
        }

        if (intval($arr['montoDolaresZelle']) > 0) {
            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr['tipoAbono'] . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '" . $arr['detalle'] . "', '1');";
        }

        if (intval($arr['montoDolaresPanama']) > 0) {
            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr['tipoAbono'] . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '" . $arr['detalle'] . "', '1');";
        }

        if (intval($arr['montoPesosEfectivo']) > 0) {
            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr['tipoAbono'] . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_peso'] . "');";
            $sql .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES  ('" . $arr['montoPesosEfectivo'] . "', 'Pesos', '" . $arr['tasa_peso'] . "', 'abono', '" . $arr['id_empleado'] . "');";
        }

        if (intval($arr['montoPesosTransferencia']) > 0) {
            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr['tipoAbono'] . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_peso'] . "');";
        }

        if (intval($arr['montoBolivaresEfectivo']) > 0) {
            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr['tipoAbono'] . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";

            $sql .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $arr['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $arr['tasa_dolar'] . "', 'abono', '" . $arr['id_empleado'] . "');";
        }

        if (intval($arr['montoBolivaresPunto']) > 0) {
            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr['tipoAbono'] . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";
        }

        if (intval($arr['montoBolivaresPagomovil']) > 0) {
            $sql .= "INSERT INTO metodos_de_pago (tipo_de_pago, moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr['tipoAbono'] . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";
        }

        if (intval($arr['montoBolivaresTransferencia']) > 0) {
            $sql .= "INSERT INTO metodos_de_pago (moneda, metodo_pago, monto, detalle, tasa) VALUES ('" . $arr['tipoAbono'] . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['detalle'] . "', '" . $arr['tasa_dolar'] . "');";
        }

        $data = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obteber Retiros
    $app->get('/retiros/{fecha}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        // Obtener retiros
        $sql = "SELECT a._id, a.moment, a.monto, a.moneda, a.metodo_pago, a.detalle_retiro, a.tasa, b.nombre empleado  FROM retiros a JOIN empleados b ON a.id_empleado = b._id WHERE a.moment LIKE '" . $args['fecha'] . "%'";

        $object['data']['retiros'] = $localConnection->goQuery($sql);

        /** FONDO */
        $sql = 'SELECT dolares, pesos, bolivares FROM caja_fondos ORDER BY _id DESC LIMIT 1';
        $fondo = $localConnection->goQuery($sql);
        $object['data']['fondo'] = $fondo;

        if (empty($fondo)) {
            $fondo[0]['dolares'] = 0;
            $fondo[0]['pesos'] = 0;
            $fondo[0]['bolivares'] = 0;
        }

        // DÓLARES EN CAJA,

        $sql = 'SELECT (SUM(monto) + ' . $fondo[0]['dolares'] . ') monto, moneda, tasa, FORMAT(((SUM(monto) / tasa)) + ' . $fondo[0]['dolares'] . ", 'C2') dolares FROM caja WHERE moneda= 'Dólares'";

        $object['data']['caja'] = $localConnection->goQuery($sql);

        // PESOS EN CAJA,
        $sql = 'SELECT (SUM(monto) + ' . $fondo[0]['pesos'] . ') monto, moneda, tasa, FORMAT((SUM(monto) + ' . $fondo[0]['pesos'] . ") / tasa, 'C2') dolares FROM caja WHERE moneda= 'Pesos'";

        array_push($object['data']['caja'], $localConnection->goQuery($sql)[0]);

        // BOLIVARES     EN CAJA,
        $sql = 'SELECT (SUM(monto) + ' . $fondo[0]['bolivares'] . ') monto, moneda, tasa, FORMAT((SUM(monto) + ' . $fondo[0]['bolivares'] . ") / tasa, 'C2') dolares FROM caja WHERE moneda= 'Bolívares'";

        array_push($object['data']['caja'], $localConnection->goQuery($sql)[0]);

        // Obtener dolares
        $sql = "SELECT SUM(monto/tasa) total  FROM metodos_de_pago WHERE moneda = 'Dólares' AND metodo_pago = 'Efectivo' AND  moment LIKE '" . $args['fecha'] . "%'";

        $object['data']['retiros_total'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Pagos Ordenes
    $app->get('/pagos-ordenes/{fecha}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = "SELECT _id, moment, monto, moneda, metodo_pago, id_orden, tasa FROM metodos_de_pago WHERE moment LIKE '" . $args['fecha'] . "%'";
        $object['data'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /** FIN RETIROS */

    /** * Diseños * */
    // REVISAR DISEÑOS APRBADOS Y RECHAZADOS
    $app->get('/diseno/revisiones/{id_empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new localDB();

        $sql = 'SELECT a.id_orden, b._id id_revision, a._id id_diseno, b.detalles, b.estatus, b.revision, c.id_wp id_cliente, c.cliente_nombre cliente FROM disenos a JOIN revisiones b ON a._id = b.id_diseno JOIN ordenes c ON c._id = a.id_orden WHERE a.id_empleado =' . $args['id_empleado'] . " AND c.status != 'entregada' AND c.status != 'cancelada' AND c.status != 'terminado'";
        $object['revisiones'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $object['total_revisiones'] = count($object['revisiones']);

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // REVISAR DISEÑO APROBADO
    $app->get('/diseno/aprobado/{id_orden}', function (Request $request, Response $response, array $args) {
        $localConnection = new localDB();
        $sql = 'SELECT revision, estatus, id_diseno FROM revisiones WHERE id_orden = ' . $args['id_orden'] . " AND estatus = 'Aprobado'";
        $resp = $localConnection->goQuery($sql);
        $localConnection->disconnect();

        if (empty($resp)) {
            $object['aprobado'] = false;
        } else {
            $object['aprobado'] = true;
            $object['data'] = $resp[0];
        }

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Guardar link de google drive
    $app->post('/disenos/link', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = "UPDATE disenos SET linkdrive = '" . $data['url'] . "' WHERE id_orden = " . $data['id'];
        $data = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener datos para la aprobación del cliente
    $app->get('/disenos/aprobacion-de-cliente/{id_orden}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        // $sql = "INSERT INTO aprobacion_clientes(id_orden, id_diseno) VALUES (" . $data["id_orden"] . ", " . $data["id_diseno"] . ");";
        $sql = 'SELECT
            a._id id_orden,    
            b._id id_diseno,
            c.revision revision,
            c.estatus estatus_aprobado, 
            a.cliente_nombre nombre_cliente
        FROM
            ordenes AS a
        LEFT JOIN disenos AS b ON a._id = b.id_orden
        LEFT JOIN revisiones AS c ON c.id_diseno = b._id 
        WHERE
            a._id = ' . $args['id_orden'];

        $object['data'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object['data']));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Guardar registro de aprobacion de clientes
    $app->post('/disenos/parobacion-de-cliente', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = "UPDATE revisiones SET estatus = 'Aprobado' WHERE id_orden = " . $data['id_orden'] . ';';
        $sql .= 'INSERT INTO aprobacion_clientes(id_orden, id_diseno) VALUES (' . $data['id_orden'] . ', ' . $data['id_diseno'] . ');';
        $object = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Guardar ajustes y personalizaciones
    $app->post('/diseno/ajustes-y-personalizaciones', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $monto_ajustes = $data['ajustes'];
        $monto_personalizacion = $data['personalizaciones'];
        $comision_ajustes = 0.2 * intval($monto_ajustes);
        $comision_pesonalizacion = 0.3 * intval($monto_personalizacion);
        $sql = '';

        // Verificar si el registro de diseños y ajuste ya existe
        $sql_tipo = "SELECT tipo FROM disenos_ajustes_y_personalizaciones WHERE tipo = 'ajuste' AND id_diseno = " . $data['id_diseno'] . ' ORDER BY tipo ASC';
        $dataRequest = $localConnection->goQuery($sql_tipo);
        if (count($dataRequest) > 0) {
            $ajuste = true;
        } else {
            $ajuste = false;
        }

        $sql_tipo = "SELECT tipo FROM disenos_ajustes_y_personalizaciones WHERE tipo = 'personalización' AND id_diseno = " . $data['id_diseno'] . ' ORDER BY tipo ASC';
        $dataRequest = $localConnection->goQuery($sql_tipo);
        $object['personalizacion'] = count($dataRequest);
        if (count($dataRequest) > 0) {
            $personalizacion = true;
        } else {
            $personalizacion = false;
        }

        $sqlord = 'SELECT id_orden, id_empleado FROM disenos WHERE _id = ' . $data['id_diseno'];
        $resultDiseno = $localConnection->goQuery($sqlord);

        // Guardar cantidades de personalizaciones y ajustes
        $sqlpa = '';
        if ($ajuste) {
            $sqlpa .= 'UPDATE disenos_ajustes_y_personalizaciones SET cantidad = ' . $monto_ajustes . ' WHERE id_diseno = ' . $data['id_diseno'] . " AND tipo = 'ajuste';";
        } else {
            $sqlpa .= 'INSERT INTO disenos_ajustes_y_personalizaciones (id_diseno, id_orden, tipo, cantidad) VALUES (' . $data['id_diseno'] . ', ' . $resultDiseno[0]['id_orden'] . ", 'ajuste', " . $monto_ajustes . ');';
        }

        if ($personalizacion) {
            $sqlpa .= 'UPDATE disenos_ajustes_y_personalizaciones SET cantidad = ' . $monto_personalizacion . ' WHERE id_diseno = ' . $data['id_diseno'] . " AND tipo = 'personalizacion';";
        } else {
            $sqlpa .= 'INSERT INTO disenos_ajustes_y_personalizaciones (id_diseno, id_orden, tipo, cantidad) VALUES (' . $data['id_diseno'] . ', ' . $resultDiseno[0]['id_orden'] . ", 'personalizacion', " . $monto_personalizacion . ');';
        }
        $data = $localConnection->goQuery($sqlpa);

        // Preparar datos para los pagos

        // Buscar datos para el guardar los pagos
        if (empty($dataRequest)) {
            $sql .= 'INSERT INTO pagos(cantidad, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (' . $monto_ajustes . ', ' . $resultDiseno[0]['id_orden'] . ", 'aprobado' , " . $comision_ajustes . ', ' . $resultDiseno[0]['id_empleado'] . ", 'ajuste');";
            $sql .= 'INSERT INTO pagos(cantidad, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (' . $monto_personalizacion . ', ' . $resultDiseno[0]['id_orden'] . ", 'aprobado' , " . $comision_pesonalizacion . ', ' . $resultDiseno[0]['id_empleado'] . ", 'personalización');";
        } else {
            $values = "monto_pago ='" . $comision_ajustes . "', cantidad = " . $monto_ajustes;
            $sql .= 'UPDATE pagos SET ' . $values . ' WHERE id_empleado = ' . $resultDiseno[0]['id_empleado'] . ' AND id_orden = ' . $resultDiseno[0]['id_orden'] . " AND detalle = 'ajuste';";
            $values = "monto_pago ='" . $comision_pesonalizacion . "', cantidad = " . $monto_personalizacion;
            $sql .= 'UPDATE pagos SET ' . $values . ' WHERE id_empleado = ' . $resultDiseno[0]['id_empleado'] . ' AND id_orden = ' . $resultDiseno[0]['id_orden'] . " AND detalle = 'personalización';";
        }
        $data = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener ajustes y personalizaciones de un diseno
    $app->get('/disenos/ajustes-y-personalizaciones/{id_diseno}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT a.tipo, a.cantidad, b.id_orden FROM disenos_ajustes_y_personalizaciones a JOIN disenos b ON b._id = a.id_diseno WHERE a.id_diseno = ' . $args['id_diseno'];
        $object = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener link de google drive
    $app->get('/disenos/link/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT linkdrive FROM disenos WHERE _id = ' . $args['id'];
        $object = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object[0]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener codigo del diseño
    $app->get('/disenos/codigo/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT codigo_diseno FROM disenos WHERE _id = ' . $args['id'];
        $object = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Guardar codigo de diseno
    $app->post('/disenos/codigo', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = "UPDATE disenos SET codigo_diseno = '" . $data['cod'] . "' WHERE id_orden = " . $data['id'];
        $data = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($sql));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener diseños sin asignar
    $app->get('/disenos', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $object['disenos']['fields'][0]['key'] = 'id';
        $object['disenos']['fields'][0]['label'] = 'Orden';

        $object['disenos']['fields'][1]['key'] = 'check';
        $object['disenos']['fields'][1]['label'] = 'Aprobado por el cliente';

        $object['disenos']['fields'][2]['key'] = 'tipo';
        $object['disenos']['fields'][2]['label'] = 'Tipo';

        $object['disenos']['fields'][3]['key'] = 'empleado';
        $object['disenos']['fields'][3]['label'] = 'Empleado';

        $object['disenos']['fields'][4]['key'] = 'vinculadas';
        $object['disenos']['fields'][4]['label'] = 'Vinculadas';

        $object['disenos']['fields'][5]['key'] = 'imagen';
        $object['disenos']['fields'][5]['label'] = 'Diseño';

        // $sql = "SELECT a.id_orden imagen, a.id_orden vinculadas, a.tipo, c.check, a.id_orden id, a.id_empleado empleado, b.responsable FROM disenos a JOIN ordenes b ON b._id = a.id_orden LEFT JOIN aprobacion_clientes c ON c.id_orden = a.id_orden AND c.id_diseno = a._id WHERE a.tipo != 'no' AND a.terminado = 0 AND b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado' ORDER BY a.id_orden DESC;";
        $sql = "SELECT DISTINCT
    a.id_orden imagen,
    a.id_orden vinculadas,
    a.tipo,
    c.estatus `check`,
    c.estatus estatus_revision,
    a.id_orden id,
    a.id_empleado empleado,
    e.nombre nombre_empleado,
    a.linkdrive
FROM
    disenos a
LEFT JOIN empleados e ON 
    e._id = a.id_empleado
JOIN ordenes b ON
    b._id = a.id_orden
LEFT JOIN revisiones c ON
    c.id_orden = a.id_orden AND c.id_diseno = a._id
WHERE
    a.tipo != 'no' AND a.terminado = 0 AND b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado'
ORDER BY
    a.id_orden
DESC;";

        $object['disenos']['items'] = $localConnection->goQuery($sql);
        $object['empleados'] = $localConnection->goQuery('SELECT * FROM empleados');

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Todos los diseños asignados
    $app->get('/disenos/asignados', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $object['disenos']['fields'][0]['key'] = 'id';
        $object['disenos']['fields'][0]['label'] = 'Orden';
        $object['disenos']['fields'][1]['key'] = 'tipo';
        $object['disenos']['fields'][1]['label'] = 'Tipo';
        $object['disenos']['fields'][2]['key'] = 'empleado';
        $object['disenos']['fields'][2]['label'] = 'Empleado';

        $sql = "SELECT a.tipo, a.id_orden, b.username, b.nombre, b._id id_empleado, FROM disenos a JOIN empleados b ON a.id_empleado = b._id  WHERE a.tipo = 'modas' OR a.tipo = 'gráfico' AND a.id_empleado > 0";

        $object['disenos']['items'] = $localConnection->goQuery($sql);
        $object['empleados'] = $localConnection->goQuery('SELECT * FROM empleados');

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Todos los diseños terminados
    $app->get('/disenos/terminados', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $object['fields'][0]['key'] = 'orden';
        $object['fields'][0]['label'] = 'Orden';

        $object['fields'][1]['key'] = 'cliente';
        $object['fields'][1]['label'] = 'Cliente';

        $object['fields'][2]['key'] = 'disenador';
        $object['fields'][2]['label'] = 'Diseñador';

        $object['fields'][3]['key'] = 'inicio';
        $object['fields'][3]['label'] = 'Inicio';

        $object['fields'][4]['key'] = 'entrega';
        $object['fields'][4]['label'] = 'Entregado';

        $object['fields'][5]['key'] = 'tipo';
        $object['fields'][5]['label'] = 'Tipo';

        $object['fields'][6]['key'] = 'codigo_diseno';
        $object['fields'][6]['label'] = 'Codigo';

        $object['fields'][7]['key'] = 'linkdrive';
        $object['fields'][7]['label'] = 'Drive';

        $object['fields'][8]['key'] = 'imagen';
        $object['fields'][8]['label'] = 'Imagen';

        // $sql = "SELECT a.id_orden orden, b.cliente_nombre cliente, c.nombre disenador, b.fecha_inicio inicio, b.fecha_entrega entrega, a.tipo, b._id imagen FROM disenos a JOIN ordenes b ON a.id_orden = b._id JOIN empleados c ON a.id_empleado = c._id WHERE a.terminado = 1;";

        $sql = "SELECT
            a.id_orden orden,
            b.cliente_nombre cliente,
            c._id id_empleado,
            c.nombre disenador,
            b.fecha_inicio inicio,
            b.fecha_entrega entrega,
            a.linkdrive,
            a.codigo_diseno,
            a.tipo,
            b.status estatus_orden,
            b._id imagen
        FROM
            disenos a
        JOIN ordenes b ON
            a.id_orden = b._id
        JOIN empleados c ON
            a.id_empleado = c._id
        WHERE
            a.terminado = 1 AND (b.status != 'entregada' OR b.status != 'cancelada')";

        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Diseñosasignados a Diseñador

    $app->get('/disenos/asignados/{id_empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $object['fields'][0]['key'] = 'id';
        $object['fields'][0]['label'] = 'Orden';

        $object['fields'][1]['key'] = 'cliente';
        $object['fields'][1]['label'] = 'Cliente';

        $object['fields'][2]['key'] = 'inicio';
        $object['fields'][2]['label'] = 'Inicio';

        $object['fields'][3]['key'] = 'revision';
        $object['fields'][3]['label'] = 'Revisión';
        $object['fields'][3]['class'] = 'text-center';

        $object['fields'][4]['key'] = 'tallas_y_personalizacion';
        $object['fields'][4]['label'] = 'Tallas y Personalización';
        $object['fields'][4]['class'] = 'text-center';

        $object['fields'][5]['key'] = 'id_orden';
        $object['fields'][5]['label'] = 'Vinculadas';
        $object['fields'][5]['class'] = 'text-center';

        $object['fields'][6]['key'] = 'codigo_diseno';
        $object['fields'][6]['label'] = 'Código Diseño';
        $object['fields'][6]['class'] = 'text-center';

        $object['fields'][7]['key'] = 'linkdrive';
        $object['fields'][7]['label'] = 'Google Drive';
        $object['fields'][7]['class'] = 'text-center';

        $object['fields'][8]['key'] = 'revision';
        $object['fields'][8]['label'] = 'Revisiones';
        $object['fields'][8]['class'] = 'text-center';

        $sql = 'SELECT 
    a._id linkdrive, 
    a.codigo_diseno, 
    a.id_orden, 
    a._id id_diseno,
    a._id tallas_y_personalizacion,
    a.id_orden id, 
    a.id_orden imagen, 
    a.id_orden revision, 
    b.cliente_nombre cliente, 
    b.fecha_inicio inicio, 
    a.tipo,
    c.estatus 
    FROM disenos a 
    LEFT JOIN revisiones c 
    ON a._id = c.id_diseno 
    JOIN ordenes b 
    ON b._id = a.id_orden 
    LEFT JOIN disenos d ON d._id = c.id_diseno
    WHERE a.id_empleado =    ' . $args['id_empleado'] . ' 
    AND a.terminado = 0 
    ORDER BY a.id_orden ASC
    ';
        $object['sql_items'] = $sql;
        $object['items'] = $localConnection->goQuery($sql);

        $sql = 'SELECT a.id_diseno id, a.revision, a.detalles detalles_revision, a.id_orden FROM revisiones a JOIN disenos b ON b._id = a.id_diseno WHERE b.id_empleado = ' . $args['id_empleado'];
        $object['sql_revisiones'] = $sql;
        $object['revisiones'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // TODO eliminar ninesys antiguo => Obtener diseños pendientes por diseñador
    $app->get('/disenos/pendientes/{id_empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $sql = 'SELECT a.id_orden orden, b.cliente_nombre cliente, b.fecha_inicio, b.status FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.id_empleado = ' . $args['id_empleado'] . ' AND terminado = 0';

        $disenos = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($disenos));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener diseños terminados por diseñador
    $app->get('/disenos/terminados/{id_empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT a.id_orden orden, b.cliente_nombre cliente, b.fecha_inicio, b.status FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.id_empleado = ' . $args['id_empleado'] . ' AND terminado = 1';

        $disenos = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($disenos));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Asignar diseñador
    $app->put('/disenos/asign/{id_orden}/{empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'UPDATE disenos SET id_empleado = ' . $args['empleado'] . ' WHERE id_orden = ' . $args['id_orden'];
        $asignacion = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($sql));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Diseñador dar diseño por terminado
    $app->put('/disenos/close/{id_orden}/{empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'UPDATE disenos SET terminado = 1 WHERE id_orden = ' . $args['id_orden'] . ' AND id_empleado = ' . $args['empleado'];
        $asignacion = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($asignacion));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /** Fin Diseños */

    /** PAGOS */
    // Terminar planilla de pago
    $app->post('/pagos/terminar-planilla', function (Request $request, Response $response, $args) {
        // $order = $request->getParsedBody();
        $localConnection = new LocalDB();
        $myDate = new CustomTime();
        $now = $myDate->today();

        $sql = "UPDATE pagos SET fecha_pago = '" . $now . "' WHERE fecha_pago IS NULL";
        $data = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // REALIZAR PAGO A EMPLEADOS
    $app->post('/pagos/pagar-a-empleados', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $myDate = new CustomTime();
        $now = $myDate->today();

        $listaDeIdPagos = explode(',', $data['id_pagos']);
        $params = '';

        // REGISTRAR PAGOS
        foreach ($listaDeIdPagos as $key => $value) {
            $params .= ' _id = ' . $value . ' OR ';
        }

        $params = substr($params, 0, -4);  // Eliminamos el ultimo OR

        $sql = "UPDATE pagos SET fecha_pago = '" . $now . "' WHERE " . $params . ';';
        $data['resp_update'] = $localConnection->goQuery($sql);

        $sql = 'SELECT * FROM pagos WHERE ' . $params;
        $registrosParaProcesar = $localConnection->goQuery($sql);

        // TODO PASAR PAGOS REGISTRADOS A HISTÓRICO
        $newSql = '';
        foreach ($registrosParaProcesar as $key => $pago) {
            $values = $pago['_id'] . ',';
            $values .= $pago['id_orden'] . ',';
            if (!is_null($pago['id_metodos_de_pago'])) {
                $values .= $pago['id_metodos_de_pago'] . ',';
            } else {
                $values .= 'null,';
            }
            if (!is_null($pago['id_lotes_detalles'])) {
                $values .= $pago['id_lotes_detalles'] . ',';
            } else {
                $values .= 'null,';
            }
            $values .= $pago['id_empleado'] . ',';

            if (!is_null($values .= $pago['cantidad'])) {
                $values .= $pago['cantidad'] . ',';
            } else {
                $values .= 'null,';
            }

            $values .= $pago['monto_pago'] . ',';
            $values .= "'" . $pago['detalle'] . "',";
            $values .= "'" . $pago['estatus'] . "',";
            $values .= "'" . $pago['fecha_pago'] . "',";
            $values .= "'" . $pago['moment'] . "'";

            $newSql .= 'INSERT INTO history.pagos(_id, id_orden, id_metodos_de_pago, id_lotes_detalles, id_empleado, cantidad, monto_pago, detalle, estatus, fecha_pago, moment) VALUES (' . $values . ');';
        }
        $data['newSql'] = $newSql;

        $data['resp_insert_history'] = $localConnection->goQuery($newSql);

        // ELIMINAR LOS ITEMS QUE SE GUARDARON EN EL HISTÓRICO
        $sqlDelete = 'DELETE FROM api.pagos WHERE _id IN (' . $data['id_pagos'] . ')';
        $data['sqlDelete'] = $sqlDelete;
        $data['resp_delete'] = $localConnection->goQuery($sqlDelete);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/pagos/pagar-a-empleados-OLD', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $myDate = new CustomTime();
        $now = $myDate->today();

        $listaDeIdPagos = explode(',', $data['id_pagos']);
        $params = '';

        foreach ($listaDeIdPagos as $key => $value) {
            $params .= ' _id = ' . $value . ' OR ';
        }
        $params = substr($params, 0, -4);  // Eliminamos el ultimo OR
        $sql = "UPDATE pagos SET fecha_pago = '" . $now . "' WHERE " . $params;

        $data = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($sql));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Lista de pagos semanales
    $app->get('/pagos/semana/disenadores', function (Request $request, Response $response, array $args) {
        // OBTERER PAGOS DE VENDEDORES
        $localConnection = new LocalDB();

        // DISEÑADORES
        $sql = 'SELECT a._id id_pago, a.id_orden, a.id_empleado, a.detalle detalle_pago, e._id id_diseno, b.nombre nombre, b.departamento, a.monto_pago pago, a.cantidad, c.name producto FROM pagos a JOIN disenos e ON e.id_empleado = a.id_empleado AND e.id_orden = a.id_orden JOIN empleados b ON b._id = a.id_empleado JOIN ordenes_productos c ON a.id_orden = c.id_orden AND c.id_category = 17 WHERE a.id_orden IS NOT NULL AND a.fecha_pago IS NULL AND a.monto_pago > 0 ORDER BY a.id_orden DESC';
        $object['data']['diseno'] = $localConnection->goQuery($sql);

        foreach ($object['data']['diseno'] as $key => $value) {
            // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
            $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
            $tmpResp = $localConnection->goQuery($sqlTMP);

            if (!empty($tmpResp)) {
                foreach ($tmpResp as $key2 => $value2) {
                    $object['data']['trabajos_adicionales'][] = $value2;
                }
            }
        }

        $trabajos_adicionales_nuevos = [];

        if (!empty($object['data']['trabajos_adicionales'])) {
            foreach ($object['data']['trabajos_adicionales'] as $trabajo_adicional) {
                $existe = false;
                foreach ($trabajos_adicionales_nuevos as $trabajo_adicional_nuevo) {
                    if ($trabajo_adicional['_id'] == $trabajo_adicional_nuevo['_id']) {
                        $existe = true;
                        break;
                    }
                }
                if (!$existe) {
                    $trabajos_adicionales_nuevos[] = $trabajo_adicional;
                }
            }
            $object['data']['trabajos_adicionales'] = $trabajos_adicionales_nuevos;
        } else {
            $object['data']['trabajos_adicionales'] = [];
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/pagos/semana/empleados', function (Request $request, Response $response, array $args) {
        // OBTERER PAGOS DE VENDEDORES
        $localConnection = new LocalDB();

        // EMPLEADOS
        $sql = 'SELECT
            a._id id_pago,
            b.id_woo cod,
            b._id id_lotes_detalles,
            b.id_orden orden,
            b.id_woo id_woo,
            d.name producto,
            d.talla,
            c._id id_empleado,
            c.nombre,
            c.comision,
            c.departamento,
            DATE_FORMAT(b.fecha_terminado, "%a") dia,
            DATE_FORMAT(b.fecha_terminado, "%v") semana,
            DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
            a.monto_pago pago,
            a.fecha_pago,
            b.unidades_solicitadas cantidad,
            TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido
            FROM
            pagos a
            JOIN lotes_detalles b ON
            a.id_lotes_detalles = b._id
            JOIN empleados c ON
            b.id_empleado = c._id
            JOIN ordenes_productos d ON
            b.id_ordenes_productos = d._id
            WHERE
            a.fecha_pago IS NULL
            ORDER BY
            c.nombre ASC,
            b.id_orden ASC,
            a._id ASC;';
        $object['data']['empleados'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/pagos/semana/vendedores', function (Request $request, Response $response, array $args) {
        // OBTERER PAGOS DE VENDEDORES
        $localConnection = new LocalDB();

        // VENDEDORES
        // $sql = "SELECT a._id id_pago, a.id_orden, a.id_empleado, a.detalle, a.cantidad, a.monto_pago pago, c.nombre, d.status, e.tipo_de_pago, DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago FROM pagos a JOIN abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado JOIN empleados c ON a.id_empleado = c._id JOIN ordenes d ON a.id_orden = d._id LEFT JOIN metodos_de_pago e ON e._id = a.id_metodos_de_pago WHERE a.fecha_pago IS NULL ORDER BY d._id ASC, a._id ASC";
        $sql = "SELECT 
    a._id AS id_pago,
    a.id_orden,
    a.id_empleado,
    a.detalle,
    a.cantidad,
    a.monto_pago AS pago,
    c.nombre,
    d.status,
    e.tipo_de_pago,
    DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago
    FROM 
    pagos a
    JOIN 
    abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado
    JOIN 
    empleados c ON a.id_empleado = c._id
    JOIN 
    ordenes d ON a.id_orden = d._id
    LEFT JOIN 
    metodos_de_pago e ON e._id = a.id_metodos_de_pago
    WHERE 
    a.fecha_pago IS NULL
    GROUP BY 
    a._id
    ORDER BY 
    d._id ASC, a._id DESC;
    ";

        $object['data']['vendedores'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Lista de pagos semanales con filtro de fechas
    $app->post('/pagos/semana', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        if ($data['fecha_inicio'] === $data['fecha_fin']) {
            $where = "e.moment LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL";
            $whereEmpleados = "b.fecha_terminado LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL ";
            // $where = "e.moment LIKE '" . $data["fecha_inicio"] . "%' ";
        } else {
            $where = "(DATE(e.moment) BETWEEN '" . $data['fecha_inicio'] . "'AND '" . $data['fecha_fin'] . "') ";
            $whereEmpleados = "b.fecha_inicio >= '" . $data['fecha_inicio'] . "' AND DATE_ADD(b.fecha_terminado, INTERVAL -1 DAY) <= '" . $data['fecha_fin'] . "' ";
        }

        $sql = "SELECT a._id id_pago, a.id_orden, a.id_empleado, a.detalle, a.cantidad, a.monto_pago pago, c.nombre, d.status, e.tipo_de_pago, DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago FROM pagos a JOIN abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado JOIN empleados c ON a.id_empleado = c._id JOIN ordenes d ON a.id_orden = d._id LEFT JOIN metodos_de_pago e ON e._id = a.id_metodos_de_pago WHERE " . $where . ' AND fecha_pago IS NULL ORDER BY d._id ASC, a._id ASC';
        $object['data']['vendedores'] = $localConnection->goQuery($sql);
        // FIN BUSCAR PAGOS DE VENDEDORES

        // OBTENER PAGOS DE EMPLEADOS
        $sql = 'SELECT
    a._id id_pago,
    b._id id_lotes_detalles,
    b.id_orden orden,
    b.id_woo id_woo,
    d.name producto,
    d.talla,
    c._id id_empleado,
    c.nombre,
    c.comision,
    c.departamento,
    DATE_FORMAT(b.fecha_terminado, "%a") dia,
    DATE_FORMAT(b.fecha_terminado, "%v") semana,
    DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
    b.unidades_solicitadas cantidad,
    a.monto_pago pago,
    a.fecha_pago,
    a.cantidad,
    TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido
    FROM
    pagos a
    JOIN lotes_detalles b ON
    a.id_lotes_detalles = b._id
    JOIN empleados c ON
    b.id_empleado = c._id
    JOIN ordenes_productos d ON
    b.id_ordenes_productos = d._id
    WHERE ' . $whereEmpleados . ' AND a.fecha_pago IS NULL 
    ORDER BY
    c.nombre ASC,
    b.id_orden ASC,
    a._id ASC;
    ';

        $object['sql']['empleados'] = $sql;
        $object['data']['empleados'] = $localConnection->goQuery($sql);
        // FIN PAGOS EMPLEADOS

        // OBTENER INFORMACION DE DISEÑADORES
        $sql = "SELECT 
    e._id id_pago,
    e.id_orden, 
    e.id_empleado,
    e.detalle detalle_pago,
    a._id id_diseno, 
    b.nombre nombre, 
    b.departamento, 
    e.monto_pago pago,
    e.cantidad,
    c.name producto 
    FROM pagos e   
    JOIN disenos a ON a.id_empleado = e.id_empleado AND a.id_orden = e.id_orden
    JOIN empleados b 
    ON b._id = e.id_empleado 
    JOIN ordenes_productos c 
    ON e.id_orden = c.id_orden AND c.category_name = 'Diseños'
    WHERE " . $where . ' AND e.monto_pago > 0 AND e.fecha_pago IS NULL';
        $object['sql']['diseno'] = $sql;
        $object['data']['diseno'] = $localConnection->goQuery($sql);

        foreach ($object['data']['diseno'] as $key => $value) {
            // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
            $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
            $tmpResp = $localConnection->goQuery($sqlTMP);
            if (!empty($tmpResp)) {
                foreach ($tmpResp as $key2 => $value2) {
                    $object['data']['trabajos_adicionales'][] = $value2;
                }
            }
        }

        $trabajos_adicionales_nuevos = [];

        if (!empty($object['data']['trabajos_adicionales'])) {
            foreach ($object['data']['trabajos_adicionales'] as $trabajo_adicional) {
                $existe = false;
                foreach ($trabajos_adicionales_nuevos as $trabajo_adicional_nuevo) {
                    if ($trabajo_adicional['_id'] == $trabajo_adicional_nuevo['_id']) {
                        $existe = true;
                        break;
                    }
                }
                if (!$existe) {
                    $trabajos_adicionales_nuevos[] = $trabajo_adicional;
                }
            }
            $object['data']['trabajos_adicionales'] = $trabajos_adicionales_nuevos;
        } else {
            $object['data']['trabajos_adicionales'] = [];
        }
        // FIN PAGOS DISEÑADORES

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/pagos/semana/OLD', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        if ($data['fecha_inicio'] === $data['fecha_fin']) {
            $where = "e.moment LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL";
            $whereEmpleados = "b.fecha_terminado LIKE '" . $data['fecha_inicio'] . "%' AND e.fecha_pago IS NULL ";
            // $where = "e.moment LIKE '" . $data["fecha_inicio"] . "%' ";
        } else {
            $where = "(DATE(e.moment) BETWEEN '" . $data['fecha_inicio'] . "'AND '" . $data['fecha_fin'] . "') ";
            $whereEmpleados = "b.fecha_terminado BETWEEN '" . $data['fecha_inicio'] . "%' AND '" . $data['fecha_fin'] . "' AND e.fecha_pago IS NULL ";

            // $where = "(DATE(e.moment) BETWEEN '" . $data["fecha_inicio"] . "' AND '" . $data["fecha_fin"] . "') ";
        }

        $sql = "SELECT a._id id_pago, a.id_orden, a.id_empleado, a.detalle, a.cantidad, a.monto_pago pago, c.nombre, d.status, e.tipo_de_pago, DATE_FORMAT(b.moment, '%d/%m/%Y') fecha_de_pago FROM pagos a JOIN abonos b ON b.id_orden = a.id_orden AND b.id_empleado = a.id_empleado JOIN empleados c ON a.id_empleado = c._id JOIN ordenes d ON a.id_orden = d._id LEFT JOIN metodos_de_pago e ON e._id = a.id_metodos_de_pago WHERE " . $where . ' AND fecha_pago IS NULL ORDER BY d._id ASC, a._id ASC';
        $object['data']['vendedores'] = $localConnection->goQuery($sql);
        // FIN BUSCAR PAGOS DE VENDEDORES

        // OBTENER PAGOS DE EMPLEADOS
        $sql = 'SELECT
    a._id id_pago,
    b._id id_lotes_detalles,
    b.id_orden orden,
    b.id_woo id_woo,
    d.name producto,
    d.talla,
    c._id id_empleado,
    c.nombre,
    c.comision,
    c.departamento,
    DATE_FORMAT(b.fecha_terminado, "%a") dia,
    DATE_FORMAT(b.fecha_terminado, "%v") semana,
    DATE_FORMAT(b.fecha_terminado, "%d/%m/%y") fecha,
    b.unidades_solicitadas cantidad,
    a.monto_pago pago,
    a.fecha_pago,
    a.cantidad,
    TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido
    FROM
    pagos a
    JOIN lotes_detalles b ON
    a.id_lotes_detalles = b._id
    JOIN empleados c ON
    b.id_empleado = c._id
    JOIN ordenes_productos d ON
    b.id_ordenes_productos = d._id
    WHERE ' . $whereEmpleados . ' AND e.fecha_pago IS NULL 
    ORDER BY
    c.nombre ASC,
    b.id_orden ASC,
    a._id ASC;
    ';

        $object['sql']['empleados'] = $sql;
        $object['data']['empleados'] = $localConnection->goQuery($sql);
        // FIN PAGOS EMPLEADOS

        // OBTENER INFORMACION DE DISEÑADORES
        $sql = "SELECT 
    e._id id_pago,
    e.id_orden, 
    e.id_empleado,
    e.detalle detalle_pago,
    a._id id_diseno, 
    b.nombre nombre, 
    b.departamento, 
    e.monto_pago pago,
    e.cantidad,
    c.name producto 
    FROM pagos e   
    JOIN disenos a ON a.id_empleado = e.id_empleado AND a.id_orden = e.id_orden
    JOIN empleados b 
    ON b._id = e.id_empleado 
    JOIN ordenes_productos c 
    ON e.id_orden = c.id_orden AND c.category_name = 'Diseños'
    WHERE " . $where . ' AND e.monto_pago > 0 AND e.fecha_pago IS NULL';
        $object['sql']['diseno'] = $sql;
        $object['data']['diseno'] = $localConnection->goQuery($sql);

        foreach ($object['data']['diseno'] as $key => $value) {
            // $sqlTMP = "SELECT a.id_orden, a.tipo, a.cantidad FROM disenos_ajustes_y_personalizaciones a WHERE a.id_orden = " . $value["id_orden"];
            $sqlTMP = 'SELECT * FROM disenos_ajustes_y_personalizaciones WHERE id_orden = ' . $value['id_orden'];
            $tmpResp = $localConnection->goQuery($sqlTMP);
            if (!empty($tmpResp)) {
                foreach ($tmpResp as $key2 => $value2) {
                    $object['data']['trabajos_adicionales'][] = $value2;
                }
            }
        }

        $trabajos_adicionales_nuevos = [];

        if (!empty($object['data']['trabajos_adicionales'])) {
            foreach ($object['data']['trabajos_adicionales'] as $trabajo_adicional) {
                $existe = false;
                foreach ($trabajos_adicionales_nuevos as $trabajo_adicional_nuevo) {
                    if ($trabajo_adicional['_id'] == $trabajo_adicional_nuevo['_id']) {
                        $existe = true;
                        break;
                    }
                }
                if (!$existe) {
                    $trabajos_adicionales_nuevos[] = $trabajo_adicional;
                }
            }
            $object['data']['trabajos_adicionales'] = $trabajos_adicionales_nuevos;
        } else {
            $object['data']['trabajos_adicionales'] = [];
        }
        // FIN PAGOS DISEÑADORES
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** FIN PAGOS */

    /** ENVIAR EMAILS */
    $app->get('/send-email', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        $recipient = 'ozcaratemcio@gmail.com';
        $subject = 'titulo del mensaje';
        $message = '<h3>Tiutlo H3</h3><p>Un parrafo...</p>';

        $headers = 'MIME-Version: 1.0' . "\r\n";
        $headers .= 'Content-type:text/html;charset=UTF-8' . "\r\n";
        $headers .= 'From: Your Name <your_email@example.com>' . "\r\n";

        $sent = mail($recipient, $subject, $message, $headers);

        if ($sent) {
            $response->getBody()->write(json_encode(['success' => true]));
        } else {
            $response->getBody()->write(json_encode(['success' => false]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    });

    $app->get('/sendmail/{id_orden}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();

        // Aquí deberías llamar a la función de la clase WooMe para enviar el correo electrónico.
        // Por ejemplo:
        $html = '<!DOCTYPE html> <html> <head> <title>Confirmación de Pedido</title> <style> /* Estilos para el correo electrónico */ body { font-family: Arial, sans-serif; background-color: #f5f5f5; } .container { max-width: 600px; margin: 0 auto; padding: 20px; background-color: #ffffff; border: 1px solid #e5e5e5; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); } h1 { color: #333333; margin-bottom: 10px; } p { color: #666666; margin-bottom: 20px; } table { width: 100%; border-collapse: collapse; margin-bottom: 20px; } th, td { border: 1px solid #e5e5e5; padding: 8px; text-align: left; } th { background-color: #f5f5f5; font-weight: bold; } .total { font-weight: bold; text-align: right; } </style> </head> <body> <div class="container"> <h1>Confirmación de Pedido</h1> <p>Estimado cliente, gracias por su pedido. A continuación, encontrará los detalles del pedido:</p> <table> <thead> <tr> <th>Producto</th> <th>Talla</th> <th>Cantidad</th> <th>Tipo de Tela</th> <th>Precio</th> </tr> </thead> <tbody> <tr> <td>Camiseta</td> <td>XL</td> <td>2</td> <td>Algodón</td> <td>$20.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> <tr> <td>Pantalón</td> <td>L</td> <td>1</td> <td>Denim</td> <td>$30.00</td> </tr> </tbody> <tfoot> <tr> <td colspan="4" class="total">Total:</td> <td>$XXX.XX</td> <!-- Reemplaza con el total real --> </tr> </tfoot> </table> <p>Gracias por elegir nuestros productos. Esperamos que disfrute de su compra.</p> </div> </body> </html>';

        // $object['dataOrder'] = $woo->getOrderById($args['id_orden']);
        // $html = '<h1>Prueba mensaje en html</h1><p>Esto es un parrafo </p> <p style="color:red">Este es ptro con texto rojo</p>';
        // $result = $woo->sendMail($args['id_orden'], $html); // Reemplaza "enviarCorreoElectronico" con la función real para enviar correos
        // Verifica el resultado y devuelve una respuesta adecuada
        if ($result) {
            $object['respuesta'] = 'Correo electrónico enviado con éxito';
        } else {
            $object['respuesta'] = 'No se envió el correo electrónico';
        }
        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** PRODUCTOS */

    // Obtener todos los productos

    $app->get('/customer/create/nine', function (Request $request, Response $response) {
        $woo = new WooMe();
        // $response->getBody()->write($woo->createCustomerNeneteen());
        $response->getBody()->write($woo->updateCustomerNine(36));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/products', function (Request $request, Response $response) {
        $woo = new WooMe();
        $response->getBody()->write($woo->getAllProducts());

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/products/categories/{id_category}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();
        $response->getBody()->write($woo->getCategoryById($args['id_category']));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener todos los productos asignados a una orden
    $app->get('/productos-asignados/{orden}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $sql = 'SELECT _id, id_orden, _id item, id_woo cod, name producto, cantidad, talla, tela, corte, precio_unitario precio, precio_woo precioWoo FROM ordenes_productos WHERE id_orden = ' . $args['orden'] . " AND category_name != 'Diseños'";

        $object['sql'] = $sql;
        $object['data'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener prodcuto por ID
    $app->get('/products/{id}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();
        $product = $woo->getProductById($args['id']);
        $response->getBody()->write(json_encode($product));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Crear un nuevo producto
    $app->post('/products/{name}/{sku}/{price}/{stock_quantity}/{categories}/{sizes}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();
        $response->getBody()->write($woo->createProduct($args['name'], $args['sku'], $args['price'], $args['stock_quantity'], $args['categories'], $args['sizes']));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Crear un nuevo producto lite
    // $app->post('/products/lite/{name}/{price}/{categories}', function (Request $request, Response $response, array $args) {
    $app->post('/products/lite', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();

        $woo = new WooMe();
        $woo->createProductLite($data['product'], $data['price'], $data['category'], $data['sku'], $data['unidades']);
        $response->getBody()->write(json_encode($woo->getAllProducts()));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Editar producto
    $app->post('/editar-producto', function (Request $request, Response $response) {
        /* $woo = new WooMe();
            $response->getBody()->write($woo->updateProduct($data["id"], $data["product"], $data["price"], $data["unidades"], $data["sku"], $data["category"]));

            $response->getBody()->write(json_encode($data)); */
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = "UPDATE
            `products`
        SET
            `product` = '" . $data['product'] . "',
            `sku` = '" . $data['sku'] . "',
            `price` = '" . $data['price'] . "',
            `stock_quantity` = '" . $data['unidades'] . "',
            `category_ids` = '" . $data['category'] . "'
        WHERE
            `_id` = " . $data['id'] . ';';

        $sql .= 'SELECT * FROM products WHERE _id = ' . $data['id'];

        $resp = $localConnection->goQuery($sql);
        $localConnection->disconnect();
        $object['response'] = $resp;
        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Actualizar Stock
    $app->put('/products/stock/{id}/{stock_quantity}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();

        $response->getBody()->write($woo->updateProductStock($args['id'], $args['stock_quantity']));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Eliminar Producto (Usamos el metodo `options` porque noo acepta metodo `delete`  da ERROR 405)
    $app->delete('/products/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $sql = 'DELETE FROM products WHERE _id =  ' . $args['id'];
        $object['response'] = json_encode($localConnection->goQuery($sql));
        $localConnection->disconnect();
        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** FIN PROIDUCTOS */

    /** MANEJO DE ATRIBUTOS PARA PRODUCTOS */

    // ASIGNAR COMISION APRODUCTO
    $app->get('/product-set-comision/{id}/{comision}', function (Request $request, Response $response, array $args) {
        $tmpConnection = new LocalDB();
        $woo = new WooMe();
        $res = $woo->updateProductComision($args['id'], $args['comision']);
        $object['res'] = $res;

        $sql1 = 'SELECT _id id_lotes_detalles, unidades_solicitadas, id_empleado FROM lotes_detalles WHERE id_woo = ' . $args['id'] . " AND departamento = 'Costura' AND fecha_terminado IS NOT NULL";
        $resp = $tmpConnection->goQuery($sql1);

        if (!empty($resp)) {
            foreach ($resp as $row) {
                $nuevo_pago = floatval($args['comision']) * intval($row['unidades_solicitadas']);
                $sql2 = 'UPDATE pagos SET monto_pago = ' . $nuevo_pago . ' WHERE id_empleado = ' . $row['id_empleado'] . ' AND fecha_pago IS NULL AND id_lotes_detalles = ' . $row['id_lotes_detalles'];
                $tmpConnection->goQuery($sql2);
            }
        }

        $tmpConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENR LOS PARODUCTOS APRA LE MANEJO DE ATRIBUTOS DE COMSIONES
    $app->get('/atributos/comisiones', function (Request $request, Response $response) {
        $woo = new WooMe();
        $object['data'] = json_decode($woo->getProductsAttr());
        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // PRUEBA DE CONEXION DIRECTA A LA BASE DE DATOS `ninetengreen` de Wordpress
    $app->get('/wp/products', function (Request $request, Response $response) {
        $woo = new WooMe();
        $object['data'] = $woo->getAllProducts();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/wp/products/{id_product}', function (Request $request, Response $response) {
        $woo = new WooMe();
        $object['data'] = $woo->getAllProducts();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /** FIN MANEJO DE ATRIBUTOS PARA PRODUCTOS */

    /** * CLIENTES */

    // ELIMINAR UN CLIENTE
    $app->post('/customers/eliminar', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        $woo = new WooMe();
        $response->getBody()->write($woo->deleteCustomer($data['customer_id']));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER TODOS LOS CLIENTES
    $app->get('/customers', function (Request $request, Response $response) {
        $woo = new WooMe();
        $object['data'] = json_decode($woo->getAllCustomesrs());
        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OPTMIZAR CLIENTES
    $app->get('/customers/optimize/{key}/{acc}', function (Request $request, Response $response) {
        $data = $request->getParsedBody();

        $woo = new WooMe();
        $response->getBody()->write($woo->deleteCustomer($data['customer_id']));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER TODAS LAS ORDENES ASOCIADAS A UN CLIENTE
    $app->get('/customers/orders/{id_customer}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();
        $object['data'] = $woo->getCustomerOrders($args['id_customer']);
        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/wp/customers', function (Request $request, Response $response) {
        $woo = new WooMe();
        $object['data'] = json_decode($woo->getAllCustomesrs());
        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/customers/orders-count/{customer_email}/{customer_id}', function (Request $request, Response $response, array $args) {
        // Buscar la cantidad de ordenes en woocommerce
        $woo = new WooMe();
        $object['ordenes_wc'] = $woo->getOrdersCount($args['customer_email']);

        // Buscar si teiene ordenes activas en el sistema de prodsucción
        $localConnection = new LocalDB();
        $sql = "SELECT COUNT(a._id) total_ordenes FROM ordenes a WHERE (a.status = 'En espera' OR a.status = 'Pausada' OR a.status = 'activa') AND a.id_wp =  " . $args['customer_id'];
        $tmpRes = $localConnection->goQuery($sql);
        $object['ordenes_ns'] = intval($tmpRes[0]['total_ordenes']);

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener Cliente por ID
    $app->get('/customers/{id}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();
        // $customer = $woo->getCustomerById($args["id"]);
        $customer = $woo->getCustomerByIdWP($args['id']);

        $response->getBody()->write(json_encode($customer));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Crear un nuevo cliente
    $app->post('/customers/{first_name}/{last_name}/{cedula}/{phone}/{email}/{address}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();

        $response->getBody()->write(
            $woo->createCustomer(
                $args['first_name'],
                $args['last_name'],
                $args['cedula'],
                $args['phone'],
                $args['email'],
                $args['address']
            )
        );
        return $response
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Actualizar Cliente
    $app->put('/customers/{id}/{first_name}/{last_name}/{cedula}/{phone}/{email}/{billing_address}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();
        $respWC = json_encode($woo->updateCustomer($args['id'], $args['first_name'], $args['last_name'], $args['cedula'], $args['phone'], $args['email'], $args['billing_address']));
        $response->getBody()->write($respWC);

        return $response
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Actualizar Cliente desde Admin
    $app->post('/customers/edit1', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $woo = new WooMe();
        $response->getBody()->write($woo->updateCustomer($data['id'], $data['first_name'], $data['last_name'], $data['cedula'], $data['phone'], $data['email'], $data['address']));

        return $response
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Nuevo cliente desde Admin
    $app->post('/customers/nuevo', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $woo = new WooMe();

        $response->getBody()->write(
            $woo->createCustomer(
                $data['first_name'],
                $data['last_name'],
                $data['cedula'],
                $data['phone'],
                $data['email'],
                $data['address']
            )
        );

        /* $response->getBody()->write($woo->updateCustomer($data["id"], $data["first_name"], $data["last_name"], $data["cedula"], $data["phone"], $data["email"], $data["address"])); */

        return $response
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS')
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** *  CATEGORIAS */
    $app->get('/categories', function (Request $request, Response $response) {
        $woo = new WooMe();

        $response->getBody()->write($woo->getAllCategories());
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** FIN CATEGORIAS */

    /** * ATRIBUTOS */
    $app->get('/attributes', function (Request $request, Response $response) {
        $woo = new WooMe();
        $response->getBody()->write($woo->getAllAttributes());

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** FIN ATRINUTOS */

    /** * TALLAS */
    $app->get('/sizes', function (Request $request, Response $response) {
        $woo = new WooMe();
        $sizes = json_decode($woo->getSizes());
        $object['data'] = $sizes;

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /** FIN TALLAS */

    /** * ORDENES */

    // Editar orden -> Actualixar datos cambio de endpoint a _null previniendo acceso

    $app->post('/orden/editar', function (Request $request, Response $response) {
        /**
         * opciones de edición
         *   - editar-talla
         *   - editar-cantidad
         *   - editar-corte
         *   - editar-tela
         *   - nuevo-producto
         *   - eliminar-producto
         */
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        switch ($data['accion']) {
            case 'editar-cantidad':
                $sql = 'UPDATE ordenes_productos SET cantidad = ' . $data['cantidad'] . ' WHERE _id = ' . $data['id'];
                $resp = $localConnection->goQuery($sql);

                // Recalcular nuevo pago_total de la orden
                $sql = 'SELECT SUM(cantidad*precio_unitario) AS total FROM ordenes_productos WHERE id_orden = ' . $data['id_orden'];

                $resp = $localConnection->goQuery($sql);
                $object['total_sql'] = $sql;
                $nuevototal = $resp[0]['total'];

                $sql = "UPDATE ordenes SET pago_total = '" . $nuevototal . "' WHERE _id = " . $data['id_orden'];
                break;

            case 'editar-talla':
                // Guardar nuevos datos
                $sql = "UPDATE ordenes_productos SET precio_unitario = '" . $data['precio'] . "', talla = '" . $data['cantidad'] . "' WHERE _id = " . $data['id'] . ';';
                $resp = $localConnection->goQuery($sql);

                // Recalcular nuevo pago_total de la orden
                $sql = 'SELECT SUM(cantidad*precio_unitario) AS total FROM ordenes_productos WHERE id_orden = ' . $data['id_orden'];

                $resp = $localConnection->goQuery($sql);
                $object['total_sql'] = $sql;
                $nuevototal = $resp[0]['total'];

                // Guardar nuevo pago_total de la orden
                $sql = "UPDATE ordenes SET pago_total = '" . $nuevototal . "' WHERE _id = " . $data['id_orden'];
                break;

            case 'editar-corte':
                $sql = "UPDATE ordenes_productos SET corte = '" . $data['cantidad'] . "' WHERE _id = " . $data['id'];
                break;

            case 'eliminar-producto':
                $sql = 'DELETE FROM ordenes_productos WHERE _id = ' . $data['id'];
                break;

            case 'editar-tela':
                // Guardar cambios
                $sql = "UPDATE ordenes_productos SET tela = '" . $data['cantidad'] . "' WHERE _id = " . $data['id'] . ';';
                break;

            case 'nuevo-producto':
                $campos = '(moment, id_orden, id_woo, precio_woo, name, cantidad, talla, corte, tela, precio_unitario)';

                // PREPARAR FECHAS
                $myDate = new CustomTime();
                $now = $myDate->today();

                $values = '(';
                $values .= "'" . $now . "',";
                $values .= '' . $data['id_orden'] . ',';
                $values .= '' . $data['id_woo'] . ',';
                $values .= '' . $data['precio_woo'] . ',';
                $values .= "'" . $data['name'] . "',";
                $values .= '' . $data['cantidad'] . ',';
                $values .= "'" . $data['talla'] . "',";
                $values .= "'" . $data['corte'] . "',";
                $values .= "'" . $data['tela'] . "',";
                $values .= '' . $data['precio_unitario'] . ')';

                $sql = 'INSERT INTO ordenes_productos ' . $campos . ' VALUES ' . $values;
                break;

            default:
                // code...
                break;
        }

        $resp = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $object['response'] = $resp;

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Actualizar estado de la orden
    $app->post('/orden/actualizar-estado', function (Request $request, Response $response, $args) {
        $order = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = "UPDATE ordenes SET status = '" . $order['estado'] . "' WHERE _id = " . $order['id'];
        $data = $localConnection->goQuery($sql);

        // Generar el regstro en Woocomemrce si la orden está terminada
        if ($order['estado'] === 'terminada' || $order['estado'] === 'entregada') {
            $sql = 'SELECT id_wp_order FROM ordenes WHERE _id = ' . $order['id'];
            $data = $localConnection->goQuery($sql);

            if (!is_null($data[0]['id_wp_order'])) {
                $woo = new WooMe();

                if ($order['estado'] === 'terminada') {
                    // UPDATE PRODUCTS QUANTITY
                    // Buscar cantidades de productos en ninesys
                    $sql = 'SELECT id_woo, cantidad FROM `ordenes_productos` WHERE id_orden = ' . $order['id'];
                    $productos = $localConnection->goQuery($sql);

                    // $data['prod_ninesys'] = $productos;

                    foreach ($productos as $key => $producto) {
                        // Buscar existencia de productos en WC
                        $tmpProd = $woo->getProductById($producto['id_woo']);

                        // Sumar cantidades de ambas fuentes
                        $tmpCantidad = $tmpProd->stock_quantity + $producto['cantidad'];

                        $woo->updateProductQuantity($producto['id_woo'], $tmpCantidad);
                    }
                }

                if ($order['estado'] === 'entregada') {
                    $r = $woo->updateOrderStatus(intval($data[0]['id_wp_order']), 'completed');
                }
            } else {
                $r['wc'] = 'La orden no tiene ID de pedido de Woocomemrce';
            }
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Buscar ordenes para asignación

    $app->get('/orden/asignacion/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $id = $args['id'];

        $sql['detalle_empleados'] = 'SELECT `dep_responsable_detalles` responsable, `dep_diseno_detalles` diseno, `dep_corte_detalles` corte, `dep_impresion_detalles` impresion, `dep_estampado_detalles` estampado, `dep_confeccion_detalles` confeccion, `dep_revision_detalles` revision FROM `ordenes` WHERE `_id` = ' . $id;

        $sql['orden'] = " SELECT _id, status, cliente_nombre, cliente_cedula, lote_id lote, fecha_inicio, fecha_entrega FROM ordenes WHERE _id = '" . $id . "' ";
        $sql['orden_personas'] = "SELECT * FROM ordenes_personas WHERE id_order = '" . $id . "'";
        $sql['ordeen_personas_productos'] = "SELECT a._id, a.id_orden, a.idp, a.prodcuto, a.cantidad, a.talla, a.tela, a.detalles, b.nombre FROM ordenes_personas_productos a JOIN ordenes_personas b ON a.idp = b.idp WHERE id_orden = '" . $id . "'";
        $sql['orden_productos'] = "SELECT _id, id_woo, name FROM ordenes_productos WHERE id_orden = '" . $id . "'";
        $sql['orden_empleados']['diseno'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_diseno = b._id WHERE a._id = '" . $id . "'";
        $sql['orden_empleados']['corte'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_corte = b._id WHERE a._id = '" . $id . "'";
        $sql['orden_empleados']['impresion'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_impresion = b._id WHERE a._id = '" . $id . "'";
        $sql['orden_empleados']['estampado'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_estampado = b._id WHERE a._id = '" . $id . "'";
        $sql['orden_empleados']['confeccion'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_confeccion = b._id WHERE a._id = '" . $id . "'";
        $sql['orden_empleados']['revision'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_revision = b._id WHERE a._id = '" . $id . "'";
        $sql['orden_empleados']['responsable'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_responsable = b._id WHERE a._id = '" . $id . "'";
        $sql['orden_empleados']['diseno'] = "SELECT b.username nombre, b._id FROM ordenes a JOIN empleados b ON a.dep_diseno = b._id WHERE a._id = '" . $id . "'";
        $sql['orden_productos_cantidad'] = "SELECT a.cantidad, a.prodcuto,. a.idp FROM ordenes_personas_productos a WHERE  id_orden = '" . $id . "'";
        $sql['lotes_detalles'] = 'SELECT producto, unidades_solicitadas, unidades_restantes, departamento, id_orden FROM lotes_detalles WHERE id_orden = ' . $id;

        $object = $localConnection->goQuery($sql['orden'])[0];

        $object['detalle_empleados'] = $localConnection->goQuery($sql['detalle_empleados'])[0];

        $object['orden_productos_cantidad'] = $localConnection->goQuery($sql['orden_productos_cantidad']);

        $object['orden_personas'] = $localConnection->goQuery($sql['orden_personas']);

        $object['orden_personas_productos'] = $localConnection->goQuery($sql['ordeen_personas_productos']);

        $object['orden_productos'] = $localConnection->goQuery($sql['orden_productos']);

        // LOTES DETALLES
        $object['lotes_detalles'] = $localConnection->goQuery($sql['lotes_detalles']);

        // EMPLEADOS
        $object['empleados']['corte'] = $localConnection->goQuery($sql['orden_empleados']['corte']);
        if ($object['empleados']['corte'] == null) {
            $object['empleados']['corte'] = '';
        }

        $object['empleados']['impresion'] = $localConnection->goQuery($sql['orden_empleados']['impresion']);
        if ($object['empleados']['impresion'] == null) {
            $object['empleados']['impresion'] = '';
        }

        $object['empleados']['estampado'] = $localConnection->goQuery($sql['orden_empleados']['estampado']);
        if ($object['empleados']['estampado'] == null) {
            $object['empleados']['estampado'] = '';
        }

        $object['empleados']['confeccion'] = $localConnection->goQuery($sql['orden_empleados']['confeccion']);
        if ($object['empleados']['confeccion'] == null) {
            $object['empleados']['confeccion'] = '';
        }

        $object['empleados']['revision'] = $localConnection->goQuery($sql['orden_empleados']['revision']);
        if ($object['empleados']['revision'] == null) {
            $object['empleados']['revision'] = '';
        }

        $object['empleados']['responsable'] = $localConnection->goQuery($sql['orden_empleados']['responsable']);
        if ($object['empleados']['responsable'] == null) {
            $object['empleados']['responsable'] = '';
        }

        $object['empleados']['diseno'] = $localConnection->goQuery($sql['orden_empleados']['diseno']);
        if ($object['empleados']['diseno'] == null) {
            $object['empleados']['diseno'] = '';
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // BUSCAR ORDEN PPARA EL ABONO
    $app->get('/ordenes/abono/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        //  Verificar existencia de la orden
        $sql = 'SELECT a.id_orden, SUM(a.abono) abono, SUM(a.descuento) descuento, b.pago_total total, SUM(a.abono) + SUM(a.descuento) total_abono_descuento, a.detalle, a.moment  FROM abonos a JOIN ordenes b ON a.id_orden = b._id WHERE a.id_orden = ' . $args['id'];
        $datosAbono = $localConnection->goQuery($sql);

        $object['data'] = $datosAbono[0];

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // PASARELAS DE PAGO
    $app->get('/metodos-de-pago', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();
        $object['data'] = $woo->getPG();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // VERIFICAR SI LA ORDEN SE PUEDE EDITAR DESDE COMERCIALIZACION
    $app->get('/ordenes/verificar-edición/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT paso  FROM lotes WHERE id_orden = ' . $args['id'];
        $datosAbono = $localConnection->goQuery($sql);
        $object = $datosAbono[0];

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/orden/abono', function (Request $request, Response $response, $args) {
        $datosAbono = $request->getParsedBody();
        $localConnection = new LocalDB();

        // OBTENER ID DEL EMPLEADO PARA GENERAR EL PAGO
        $sql = 'SELECT responsable FROM ordenes WHERE _id = ' . $datosAbono['id'];
        $id_vendedor = $localConnection->goQuery($sql)[0]['responsable'];

        $sql = 'SELECT pago_abono FROM ordenes WHERE _id = ' . $datosAbono['id'];
        $primerAbono = $localConnection->goQuery($sql);
        $totalAbono = floatval($datosAbono['abono']);

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $values = "'" . $now . "',";
        $values .= "'" . $datosAbono['id'] . "',";
        $values .= "'" . $totalAbono . "',";
        $values .= "'" . $datosAbono['descuento'] . "',";
        $values .= "'" . $datosAbono['empleado'] . "'";

        $sql = 'INSERT INTO abonos(moment, id_orden, abono, descuento, id_empleado) VALUES (' . $values . ')';
        $data = $localConnection->goQuery($sql);

        // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
        $sql_metodos_pago = '';
        if (intval($datosAbono['montoDolaresEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Dólares', 'Efectivo', '" . $datosAbono['montoDolaresEfectivo'] . "', '1');";
            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono['montoDolaresEfectivo'] . "', 'Dólares', 1, '" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['responsable'] . "');";
        }

        if (intval($datosAbono['montoDolaresZelle']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detalleZelle'] . "',  '" . $datosAbono['id'] . "', 'Dólares', 'Zelle', '" . $datosAbono['montoDolaresZelle'] . "', '1');";
        }

        if (intval($datosAbono['montoDolaresPanama']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (detalle, tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detallePanama'] . "', '" . $datosAbono['id'] . "', 'Dólares', 'Panamá', '" . $datosAbono['montoDolaresPanama'] . "', '1');";
        }

        if (intval($datosAbono['montoPesosEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Pesos', 'Efectivo', '" . $datosAbono['montoPesosEfectivo'] . "', '" . $datosAbono['tasa_peso'] . "');";
            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono['montoPesosEfectivo'] . "', 'Pesos', '" . $datosAbono['tasa_peso'] . "', '" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['responsable'] . "');";
        }

        if (intval($datosAbono['montoPesosTransferencia']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detallePesosTransferencia'] . "', '" . $datosAbono['id'] . "', 'Pesos', 'Transferencia', '" . $datosAbono['montoPesosTransferencia'] . "', '" . $datosAbono['tasa_peso'] . "');";
        }

        if (intval($datosAbono['montoBolivaresEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Efectivo', '" . $datosAbono['montoBolivaresEfectivo'] . "', '" . $datosAbono['tasa_dolar'] . "');";

            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado) VALUES ('" . $datosAbono['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $datosAbono['tasa_dolar'] . "', '" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['responsable'] . "');";
        }

        if (intval($datosAbono['montoBolivaresPunto']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Punto', '" . $datosAbono['montoBolivaresPunto'] . "', '" . $datosAbono['tasa_dolar'] . "');";
        }

        if (intval($datosAbono['montoBolivaresPagomovil']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detallePagomovil'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Pagomovil', '" . $datosAbono['montoBolivaresPagomovil'] . "', '" . $datosAbono['tasa_dolar'] . "');";
        }

        if (intval($datosAbono['montoBolivaresTransferencia']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (tipo_de_pago, detalle, id_orden, moneda, metodo_pago, monto, tasa) VALUES ('" . $datosAbono['tipoAbono'] . "', '" . $datosAbono['detalleBolivaresTransferencia'] . "', '" . $datosAbono['id'] . "', 'Bolívares', 'Transferencia', '" . $datosAbono['montoBolivaresTransferencia'] . "', '" . $datosAbono['tasa_dolar'] . "');";
        }

        $object['metodos_pago'] = $localConnection->goQuery($sql_metodos_pago);

        // OBTENER ULTIMO DE LA TABLA metodos_de_pago
        $sql_max_id = 'SELECT MAX(_id) last_id FROM metodos_de_pago';
        $last_id_pago = $localConnection->goQuery($sql_max_id)[0]['last_id'];

        // GUARDAR PAGO
        $comision_vendedor = number_format((floatval($datosAbono['abono']) * 5 / 100), 2);
        $sql = "INSERT INTO pagos (detalle, estatus, monto_pago, id_empleado, id_orden, id_metodos_de_pago) VALUES ('Comercialización', 'aprobado', " . $comision_vendedor . ', ' . $id_vendedor . ', ' . $datosAbono['id'] . ', ' . $last_id_pago . ')';

        $object['response_SET_pago'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // GUARDAR OBSERVACIONES DESDE EDITAR EN COMERCIALIZACION
    $app->post('/orden/edit/obs', function (Request $request, Response $response, $args) {
        $datosObs = $request->getParsedBody();
        $localConnection = new LocalDB();

        // $sql = "UPDATE ordenes SET observaciones = '" . $datosObs["obs"] . "'  WHERE _id = " . $datosObs["id"];
        $sql = "UPDATE ordenes SET observaciones = 'Editada sin concentimiento por " . $datosObs['empleado'] . "'  WHERE _id = " . $datosObs['id'];
        $data = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // BUSCAR DETALLES DEL ABONO
    $app->get('/ordenes/abono-detale/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $object['fields'][0]['key'] = 'moment';
        $object['fields'][0]['label'] = 'Fecha y hora';
        $object['fields'][0]['sortable'] = true;

        $object['fields'][1]['key'] = 'abono';
        $object['fields'][1]['label'] = 'Abono';
        $object['fields'][1]['sortable'] = true;

        $object['fields'][2]['key'] = 'descuento';
        $object['fields'][2]['label'] = 'Descuento';
        $object['fields'][2]['sortable'] = true;
        //  Verificar existencia de la orden
        $sql = 'SELECT _id id_Abono, abono abono, descuento, moment FROM abonos  WHERE id_orden = ' . $args['id'] . ' GROUP BY _id, id_orden';
        $datosAbono = $localConnection->goQuery($sql);
        $object['items'] = $datosAbono;

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // REPORTE PAGOS DE EMPLEADOS
    $app->get('/reportes/resumen/empleados/{id_empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT departamento FROM empleados WHERE _id = ' . $args['id_empleado'];
        $departamento = $localConnection->goQuery($sql)[0]['departamento'];
        $object['departamento'] = $departamento;

        if ($departamento === 'Costura') {
            $sql = "SELECT
        a._id id_lote_detalles,
        a.id_orden,
        a.id_woo,
        a.id_orden detalle,     
        a.fecha_inicio fecha_inicio_ts,
        a.fecha_terminado fecha_terminado_ts,
        DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') fecha_inicio,
        DATE_FORMAT(a.fecha_inicio, '%h:%i %p') hora_inicio,
        DATE_FORMAT(a.fecha_terminado, '%d/%m/%Y') fecha_terminado,
        DATE_FORMAT(a.fecha_terminado, '%h:%i %p') hora_terminado,    
        TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido,
        b.departamento,
        a.progreso,        
        c.name producto,
        c.talla,
        c.corte,
        c.tela,
        c.cantidad,
        b.comision,
        d.fecha_pago,
        d.monto_pago,
        0 calculo_pago
        FROM
        lotes_detalles a
        LEFT JOIN empleados b ON b._id = a.id_empleado
        JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
        LEFT JOIN pagos d ON d.id_lotes_detalles = a._id
        WHERE 
        d.fecha_pago IS NULL AND 
        d.id_empleado = " . $args['id_empleado'] . ' ORDER BY a.id_orden ASC';

            $ordenes = $localConnection->goQuery($sql);
            $object['ordenes'] = $ordenes;

            // Buscar comision de productos en woocommerce y recalcular `calculo_pago`
            $tmpOrdenes = null;
            foreach ($object['ordenes'] as $key => $orden) {
                $tmpOrdenes[$key] = $orden;
                $idwc = intval($orden['id_woo']);
                $woo = new WooMe();
                $producto = $woo->getProductById($idwc);

                if (isset($producto->attributes[0]->options)) {
                    $comision = $producto->attributes[0]->options[0];
                    $tmpOrdenes[$key]['comision'] = $producto->attributes[0]->options[0];
                    $comision = floatval($comision) * intval($orden['cantidad']);
                    $tmpOrdenes[$key]['calculo_pago'] = $comision;
                } else {
                    $comision = 0;
                }
            }
            $object['ordenes'] = $tmpOrdenes;
            $object['ordenes_semana'] = $tmpOrdenes;
        }

        if ($departamento === 'Corte' || $departamento === 'Estampado' || $departamento === 'Limpieza' || $departamento === 'Revisión' || $departamento === 'Impresión') {
            // REporte todo lo no pagado
            $sql = "SELECT
        a._id id_lotes_detalles,
        a.id_orden,
        a.id_orden detalle,
        a.fecha_inicio fecha_inicio_ts,
        a.fecha_terminado fecha_terminado_ts,
        DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') fecha_inicio,
        DATE_FORMAT(a.fecha_inicio, '%h:%i %p') hora_inicio,
        DATE_FORMAT(a.fecha_terminado, '%d/%m/%Y') fecha_terminado,
        DATE_FORMAT(a.fecha_terminado, '%h:%i %p') hora_terminado,
        TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido,
        a.unidades_solicitadas cantidad,
        b.name producto,
        FORMAT(b.cantidad * c.comision, 2) AS calculo_pago
        FROM
        lotes_detalles a
        JOIN empleados c ON
        a.id_empleado = c._id
        JOIN ordenes_productos b ON
        a.id_ordenes_productos = b._id
        JOIN pagos d ON
        d.id_lotes_detalles = a._id
        WHERE  d.fecha_pago IS NULL AND 
        a.id_empleado = " . $args['id_empleado'] . ' ORDER BY a.id_orden ASC';

            $ordenes = $localConnection->goQuery($sql);
            $object['ordenes'] = $ordenes;

            $sql = "SELECT
        a._id id_lote_detalles,
        a.id_orden,
        a.id_orden detalle,     
        a.fecha_inicio fecha_inicio_ts,
        a.fecha_terminado fecha_terminado_ts,
        DATE_FORMAT(a.fecha_inicio, '%d/%m/%Y') fecha_inicio,
        DATE_FORMAT(a.fecha_inicio, '%h:%i %p') hora_inicio,
        DATE_FORMAT(a.fecha_terminado, '%d/%m/%Y') fecha_terminado,
        DATE_FORMAT(a.fecha_terminado, '%h:%i %p') hora_terminado,    
        TIMEDIFF(fecha_terminado, fecha_inicio) tiempo_transcurrido,
        b.departamento,
        a.progreso,        
        c.name producto,
        c.talla,
        c.corte,
        c.tela,
        c.cantidad,
        b.comision,
        d.fecha_pago,
        d.monto_pago,
        FORMAT(c.cantidad * b.comision, 2) AS calculo_pago                

        FROM
        pagos d
        LEFT JOIN lotes_detalles a ON d.id_lotes_detalles = a._id
        JOIN empleados b ON b._id = a.id_empleado
        JOIN ordenes_productos c ON c._id = a.id_ordenes_productos            
        WHERE
        b._id = " . $args['id_empleado'] . ' AND  d.fecha_pago IS NULL ORDER BY a.id_orden ASC';
            $ordenes = $localConnection->goQuery($sql);
            $object['ordenes_semana'] = $ordenes;
        }

        if ($departamento === 'Comercialización' || $departamento === 'Administración') {
            $sql = "SELECT 
        a._id id_pagos,
        a.id_orden,
        DATE_FORMAT(a.moment, '%d/%m/%Y') fecha_de_pago,
        b.tipo_de_pago,
        a.monto_pago monto_pago 
        FROM pagos a 
        LEFT JOIN metodos_de_pago b ON b._id = a.id_metodos_de_pago 
        WHERE a.id_empleado = " . $args['id_empleado'] . ' AND a.fecha_pago IS NULL';

            $ordenes = $localConnection->goQuery($sql);
            $object['ordenes_semana'] = $ordenes;
        }

        if ($departamento === 'Diseño') {
            /*  $sql = "SELECT
                  a._id disenos,
                  b.id_woo,
                  a.id_orden,
                  b.cantidad unidades_solicitadas,
                  e.cantidad unidades_arreglos,
                  d.estatus,
                  b.name producto,
                  e.tipo tipo_arreglo,
                  b.category_name
                  FROM disenos a
                  JOIN revisiones d ON a._id = d.id_diseno
                  JOIN empleados c ON a.id_empleado = c._id
                  JOIN ordenes_productos b
                  ON a.id_orden = b.id_orden AND b.category_name = 'Diseños'
                  LEFT JOIN disenos_ajustes_y_personalizaciones e ON e.id_orden = a.id_orden AND e.id_diseno = a._id

                  WHERE a.id_empleado = " . $args["id_empleado"] . "
                  AND YEARWEEK(a.moment)=YEARWEEK(NOW()) ORDER BY a.id_orden ASC;"; */

            $sql = "SELECT
                                                a._id id_pago,
                                                a.id_orden,
                                                a.cantidad,
                                                a.fecha_pago fecha_terminado,
                                                a.detalle producto,
                                                b.tipo tipo_arreglo,
                                                a.monto_pago calculo_pago,
                                                CASE
                                                WHEN b.tipo IS NOT NULL THEN b.tipo
                                                ELSE 'Diseño'
                                                END AS tipo
                                                FROM
                                                pagos a
                                                LEFT JOIN disenos_ajustes_y_personalizaciones b 
                                                ON b.id_orden = a.id_orden
                                                WHERE
                                                a.fecha_pago IS NULL AND a.id_empleado = " . $args['id_empleado'] . ' 
                                                GROUP BY a._id
                                                ORDER BY 
                                                a._id
                                                DESC;';

            $ordenes = $localConnection->goQuery($sql);

            // Buscar monto de la comusión del producto en Woocommerce
            $woo = new WooMe();
            /* foreach ($ordenes as $key => $orden) {
// Verificar si el pago es diseño o personalizacion-ajuste
                if ($orden["tipo_arreglo"] === null) {
                    $woomeResponse = $woo->getProductById($orden["id_woo"]);
                    $ordenes[$key]["calculo_pago"] = $woomeResponse->attributes[0]->options[0];
                    } else {
// calcular ajustes y personalizaciones
                        $unidades_arreglo = intval($orden["unidades_arreglos"]);
                        switch ($orden["tipo_arreglo"]) {
                            case 'ajuste':
                            $calc = $unidades_arreglo * 0.2;
                            $ordenes[$key]["unidades_solicitadas"] = $ordenes[$key]["unidades_arreglos"];
                            $ordenes[$key]["producto"] = $ordenes[$key]["tipo_arreglo"];
                            $ordenes[$key]["calculo_pago"] = number_format($calc, 2);
                            break;
                            case 'personalizacion':
                            $calc = $unidades_arreglo * 0.3;
                            $ordenes[$key]["unidades_solicitadas"] = $ordenes[$key]["unidades_arreglos"];
                            $ordenes[$key]["producto"] = $ordenes[$key]["tipo_arreglo"];
                            $ordenes[$key]["calculo_pago"] = number_format($calc, 2);
                            break;
                        }

                    }
                } */
            $object['ordenes'] = $ordenes;
            $object['ordenes_semana'] = $ordenes;
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // REPORTE SEMANAL DE PAGOS Y ABONOS
    $app->get('/comercializacion/reportes/pagos-abonos', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $object['fields'][0]['key'] = 'id_orden';
        $object['fields'][0]['label'] = 'Orden';

        $object['fields'][1]['key'] = 'moment';
        $object['fields'][1]['label'] = 'Fecha y hora';

        $object['fields'][2]['key'] = 'abono';
        $object['fields'][2]['label'] = 'Abono';

        $object['fields'][3]['key'] = 'descuento';
        $object['fields'][3]['label'] = 'Descuento';

        $sql = 'SELECT a._id, a.id_orden, a.abono abono, a.descuento, a.moment FROM abonos a  WHERE YEARWEEK(a.moment)=YEARWEEK(NOW()) ORDER BY a.id_orden ASC';
        $datosAbono = $localConnection->goQuery($sql);
        $object['items'] = $datosAbono;

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // BUSCAR ORDEN POR ID

    $app->get('/ordenes/reporte/{id}', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        $localConnection = new LocalDB();

        //  Verificar existencia de la orden
        $sql = 'SELECT _id FROM ordenes WHERE _id=' . $id;
        $resp = $localConnection->goQuery($sql);

        if (!$resp) {
            $object = $resp;
        } else {
            // Buscar datos del cliente en Woocommerce ...
            $sql = 'SELECT id_wp FROM ordenes WHERE _id  = ' . $id;
            $id_wp = $localConnection->goQuery($sql);
            $id_customer = $id_wp[0]['id_wp'];

            $woo = new WooMe();
            $object = array();

            // Buscar datos de la orden
            $sql = 'SELECT a._id, a.status, a.cliente_nombre, a.cliente_cedula, a.fecha_inicio, a.fecha_entrega, a.observaciones, a.pago_total, a.pago_abono FROM ordenes a  WHERE _id =  ' . $id;
            $object['orden'] = $localConnection->goQuery($sql);

            // Buscar datos del diseño
            // $sql = "SELECT tipo FROM disenos WHERE id_orden =  " . $id;
            $sql = 'SELECT a._id id_diseno, a.tipo, a.id_orden, b.revision revision FROM disenos a JOIN revisiones b ON b.id_diseno = a._id WHERE a.id_orden =' . $id;
            $object['diseno'] = $localConnection->goQuery($sql);

            // Buscar datos del cliente
            $object['customer'][0] = $woo->getCustomerById($id_customer);

            // Buscar datos de productos
            $sql = 'SELECT _id, name, id_woo cod, cantidad, talla, corte, precio_unitario precio FROM `ordenes_productos` WHERE id_orden = ' . $id;
            $object['productos'] = $localConnection->goQuery($sql);
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // BUSCAR ORDEN POR ID
    // Función para obtener la respuesta de /buscar
    function obtenerRespuestaBuscar($id, $email = null): array
    {
        $object = array();
        $localConnection = new LocalDB();

        //  Verificar existencia de la orden
        $sql = 'SELECT _id FROM ordenes WHERE _id=' . $id;
        $resp = $localConnection->goQuery($sql);

        if (!$resp) {
            $object = $resp;
        } else {
            // Buscar datos del cliente en Woocommerce ...
            $sql = 'SELECT id_wp FROM ordenes WHERE _id  = ' . $id;
            $id_wp = $localConnection->goQuery($sql);
            $id_customer = $id_wp[0]['id_wp'];
            $id_customer = $id_wp[0]['id_wp'];

            // $object["id_customer"] = $id_customer;

            $woo = new WooMe();
            // Buscar datos del cliente
            // $object["customer"][0] = $woo->getCustomerById($id_customer);
            $data = $woo->getCustomerByIdWP($id_customer);
            $customer = json_decode(json_encode($data), true);
            $object['customer']['data'] = $customer;

            $object['customer']['nombre'] = $customer[0]['billing_first_name'] . ' ' . $customer[0]['billing_last_name'];
            $object['customer']['direccion'] = $customer[0]['billing_address_1'];
            $object['customer']['email'] = $customer[0]['billing_email'];
            $object['customer']['cedula'] = $customer[0]['billing_postcode'];
            $object['customer']['telefono'] = $customer[0]['billing_phone'];

            // Buscar datos de la orden!
            $sql = 'SELECT a._id, a.status, a.cliente_nombre, b.cedula, a.fecha_inicio, a.fecha_entrega, a.observaciones, a.pago_total, a.pago_abono, a.pago_descuento FROM ordenes a JOIN customers b ON a.id_wp = b._id  WHERE a._id =  ' . $id;
            $object['orden'] = $localConnection->goQuery($sql);

            // Buscar datos del diseño
            $sql = 'SELECT tipo FROM disenos WHERE id_orden =  ' . $id;
            $object['diseno'] = $localConnection->goQuery($sql);
            if (empty($object['diseno'])) {
                $object['diseno'][]['tipo'] = 'Ninguno';
            }

            // Buscar datos de productos
            $sql = 'SELECT
                op._id,
                op.name,
                pr.sku cod,
                op.id_woo,
                op.cantidad,
                op.talla,
                op.tela,
                op.corte,
                op.precio_unitario precio
            FROM
                ordenes_productos op
            LEFT JOIN products pr ON pr._id = op.id_woo
            WHERE
                id_orden = ' . $id;
            $object['productos'] = $localConnection->goQuery($sql);

            $object['productos_count'] = count($object['productos']);

            $object['conterwoo'] = count($object['productos']);

            // Buscar SKU de los productos
            /* $counter = 0;
                  foreach ($object['productos'] as $key => $value) { // if (!is_object($value->id_woo)) {
                      // $wcprod = $woo->getProductSKU($value->id_woo);
                      $object['productos'][$key]['cod'] = $woo->getProductSKU(intval($value['id_woo']));
                      // }
                      $counter++;
                  } */

            // Crear estructura del email de bienvenida:
            /* if ($email) {
                      $emailCliente = new EmailClienteBienvenida($object);
                      $emailContent = $emailCliente->obtenerContenido();
                      $object = $emailContent;
                      $contentType = 'text/html';
                  } else {
                      $object = json_encode($object);
                  $contentType = 'application/json';
                  } */
        }

        $localConnection->disconnect();

        $object = json_encode($object);
        $contentType = 'application/json';
        return array('object' => $object, 'contentType' => $contentType);
    }

    $app->get('/buscar/{id}[/{email}]', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        $email = isset($args['email']) ? $args['email'] : null;

        $result = obtenerRespuestaBuscar($id, $email);

        $response->getBody()->write($result['object']);

        return $response
            ->withHeader('Content-Type', $result['contentType'])
            ->withStatus(200);
    });

    /*$app->get('/buscar_old/{id}[/{email}]', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $id = $args["id"];
        $object = array();

//  Verificar existencia de la orden
        $sql = "SELECT _id FROM ordenes WHERE _id=" . $id;
        $resp = $localConnection->goQuery($sql);

        if (!$resp) {
            $object = $resp;
            } else {
// Buscar datos del cliente en Woocommerce ...
                $sql = "SELECT id_wp FROM ordenes WHERE _id  = " . $id;
                $id_wp = $localConnection->goQuery($sql);
                $id_customer = $id_wp[0]["id_wp"];

                $object["id_customer"] = $id_customer;

                $woo = new WooMe();
// Buscar datos del cliente
// $object["customer"][0] = $woo->getCustomerById($id_customer);
                $data = $woo->getCustomerById($id_customer);
                $customer = json_decode(json_encode($data), true);

                $object["customer"]["nombre"] = $customer["first_name"] . " " . $customer["last_name"];
                $object["customer"]["direccion"] = $customer["billing"]["address_1"];
                $object["customer"]["email"] = $customer["billing"]["email"];
                $object["customer"]["cedula"] = $customer["billing"]["postcode"];
                $object["customer"]["telefono"] = $customer["billing"]["phone"];

// Buscar datos de la orden
                $sql = "SELECT a._id, a.status, a.cliente_nombre, a.cliente_cedula, a.fecha_inicio, a.fecha_entrega, a.observaciones, a.pago_total, a.pago_abono, a.pago_descuento FROM ordenes a  WHERE _id =  " . $id;
                $object["orden"] = $localConnection->goQuery($sql);

// Buscar datos del diseño
                $sql = "SELECT tipo FROM disenos WHERE id_orden =  " . $id;
                $object['diseno'] = $localConnection->goQuery($sql);
                if (empty($object['diseno'])) {
                    $object['diseno'][]['tipo'] = "Ninguno";
                }

// Buscar datos de productos
                $sql = "SELECT _id, name, id_woo cod, cantidad, talla, tela, corte, precio_unitario precio FROM `ordenes_productos` WHERE id_orden = " . $id;
                $object['productos'] = $localConnection->goQuery($sql);

// Crear estructura del email de bienvenida:
                if (isset($args['email'])) {
                    $emailCliente = new EmailClienteBienvenida($object);
                    $email = $emailCliente->obtenerContenido();
                    $object = $email;
// $object = json_encode($email);
                    $contentType = 'text/html';
                    } else {
                        $object = json_encode($object);
                        $contentType = 'application/json';
                    }
                }

                $localConnection->disconnect();

                $response->getBody()->write($object);
                return $response
                ->withHeader('Content-Type', $contentType)
                ->withStatus(200);
            });*/

    $app->get('/ruta2', function (Request $request, Response $response, array $args) {
        // Llamamos a la función que encapsula la lógica de /buscar
        $resultBuscar = obtenerRespuestaBuscar(303, 'true');

        // Modificamos la respuesta si es necesario
        /* $resultBuscar['object'] = json_decode($resultBuscar['object'], true);
            $resultBuscar['object']['modificado_en_ruta2'] = true;
            $resultBuscar['object'] = json_encode($resultBuscar['object']); */

        $response->getBody()->write($resultBuscar['object']);
        return $response
            ->withHeader('Content-Type', $resultBuscar['contentType'])
            ->withStatus(200);
    });

    // ORDENES ACTIVAS, TERMINADAS Y PAUSADAS
    $app->get('/comercializacion/ordenes/reporte', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        // BUSCAR ORENES EN CURSO
        $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'activa' OR status = 'pausada' OR status = 'En espera' OR status = 'terminada'  ORDER BY _id DESC";
        $object['items'] = $localConnection->goQuery($sql);

        $sql = 'SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC';
        $object['vinculadas'] = $localConnection->goQuery($sql);

        // CREAR CAMPOS DE LA TABLA
        $object['fields'][0]['key'] = '_id';
        $object['fields'][0]['label'] = 'Orden';

        $object['fields'][1]['key'] = 'cliente_nombre';
        $object['fields'][1]['label'] = 'Cliente';

        $object['fields'][2]['key'] = 'status';
        $object['fields'][2]['label'] = 'Status';

        $object['fields'][3]['key'] = 'vinculada';
        $object['fields'][3]['label'] = 'Vinculadas';

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // ORDENES TERMNADAS Y NO ENTREGADAS
    $app->get('/comercializacion/ordenes/reporte/terminadas/{rango}', function (Request $request, Response $response, array $args) {
        $object['rango'] = $args['rango'];
        $localConnection = new LocalDB();

        // PREPARAR FECHAS
        $myDate = new CustomTime($args['rango']);
        $now = $myDate->today();
        $before = $myDate->before();
        $object['moment-today'] = $now;
        $object['moment-before'] = $before;
        $momentInit = $now;
        $momentEnd = $before;

        // BUSCAR ORENES EN CURSO
        $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'terminada' AND moment BETWEEN '" . $momentEnd . "' AND '" . $momentInit . " '   ORDER BY _id ASC";
        $object['items'] = $localConnection->goQuery($sql);

        $sql = 'SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC';
        $object['vinculadas'] = $localConnection->goQuery($sql);

        // CREAR CAMPOS DE LA TABLA
        $object['fields'][0]['key'] = '_id';
        $object['fields'][0]['label'] = 'Orden';

        $object['fields'][1]['key'] = 'cliente_nombre';
        $object['fields'][1]['label'] = 'Cliente';

        $object['fields'][2]['key'] = 'status';
        $object['fields'][2]['label'] = 'Status';

        $object['fields'][3]['key'] = 'vinculada';
        $object['fields'][3]['label'] = 'Vinculadas';

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // ORDENES ENTREGADAS
    $app->get('/comercializacion/ordenes/reporte/entregadas/{rango}', function (Request $request, Response $response, array $args) {
        $object['rango'] = $args['rango'];
        $localConnection = new LocalDB();

        // PREPARAR FECHAS
        $myDate = new CustomTime($args['rango']);
        $now = $myDate->today();
        $before = $myDate->before();
        $object['moment-today'] = $now;
        $object['moment-before'] = $before;
        $momentInit = $now;
        $momentEnd = $before;

        // BUSCAR ORENES EN CURSO
        // $sql = "SELECT _id, status, cliente_nombre, _id vinculada from ordenes WHERE status = 'entregada' AND moment BETWEEN '" . $momentEnd . "' AND '" . $momentInit . " '   ORDER BY _id ASC";
        $sql = 'SELECT _id, status, cliente_nombre, _id vinculada from ordenes ORDER BY _id ASC';

        $object['items'] = $localConnection->goQuery($sql);

        $sql = 'SELECT _id, id_child, id_father from ordenes_vinculadas ORDER BY id_father ASC';

        $object['vinculadas'] = $localConnection->goQuery($sql);

        // CREAR CAMPOS DE LA TABLA
        $object['fields'][0]['key'] = '_id';
        $object['fields'][0]['label'] = 'Orden';

        $object['fields'][1]['key'] = 'cliente_nombre';
        $object['fields'][1]['label'] = 'Cliente';

        $object['fields'][2]['key'] = 'status';
        $object['fields'][2]['label'] = 'Status';

        $object['fields'][3]['key'] = 'vinculada';
        $object['fields'][3]['label'] = 'Vinculadas';

        $localConnection->disconnect();

        // $response->getBody()->write(json_encode($object["id_empleado"][0]["dep"]));
        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // CREAR NUEVA ORDEN ANTES DE VENTA AL DETAL EN LA TIENDA
    $app->post('/ordenes/nueva', function (Request $request, Response $response, $arg) {
        $newJson = $request->getParsedBody();
        $misProductos = json_decode($newJson['productos'], true);
        $localConnection = new LocalDB();

        // $misProductosLotesDealles = json_decode($newJson['productos_lotes_detalles'], true);
        $count = count($misProductos);

        $arr['id_wp'] = json_decode($newJson['id']);
        $arr['nombre'] = json_decode($newJson['nombre']);
        $arr['vinculada'] = json_decode($newJson['vinculada']);
        $arr['apellido'] = json_decode($newJson['apellido']);
        $arr['cedula'] = json_decode($newJson['cedula']);
        $arr['telefono'] = json_decode($newJson['telefono']);
        $arr['email'] = json_decode($newJson['email']);
        $arr['direccion'] = json_decode($newJson['direccion']);
        $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
        $arr['misProductos'] = json_decode($newJson['productos'], true);
        $arr['obs'] = json_decode($newJson['obs']);
        $arr['total'] = json_decode($newJson['total']);
        $arr['abono'] = json_decode($newJson['abono']);
        $arr['descuento'] = json_decode($newJson['descuento']);
        $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
        $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
        $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
        $arr['responsable'] = json_decode($newJson['responsable']);
        $arr['sales_commission'] = json_decode($newJson['sales_commission']);

        // RECIBIR LOS METODOS DE PAGO
        $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
        $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
        $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
        $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
        $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
        $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
        $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
        $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
        $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
        $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
        $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
        $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
        $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
        $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
        $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
        $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
        $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
        $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
        $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
        $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);

        $arr['hoy'] = date('d/m/Y');
        // $object["arr"] = $arr;
        $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        // Crear nueva orden en Woocommerce
        /* $woo = new WooMe();
            $orderWC = $woo->createOrder($arr, $newJson);
            $object["create_product_WC"] = $orderWC; */
        $orderWC = 0;
        /* $response->getBody()->write(json_encode($object));
            return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); */
        /* *** */

        /* Enviar email al cliente */
        // $woo = new WooMe();
        // Por ejemplo:
        // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

        /* Craer orden en nunesys */
        $sql = 'INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status ) VALUES (' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', '" . addslashes($newJson['obs']) . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'En espera' )";

        $object['nueva_oreden_response'] = json_encode($localConnection->goQuery($sql));

        // Obtenr id de la orden creada
        $last = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes');
        $last_id = intval($last[0]['id']);
        $object['last_id'] = $last_id;

        // Guardar orden vinculada
        if ($arr['vinculada'] != 0 || $arr['vinculada'] != '0') {
            $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr['vinculada'] . ', ' . $last_id . ')';
            $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
        }

        // Crear abono inicial de la orden
        $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson['abono'] . "', '" . $newJson['descuento'] . "');";
        $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));

        // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
        // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono
        if (floatval($newJson['abono']) > 0) {
            // $object['sales_commission_ISSET'][] = $arg["sales_commission"];
            $pago_vendedor = floatval($newJson['abono']) * 5 / 100;
            $pago_vendedor = number_format($pago_vendedor, 2);
            $sql = "INSERT INTO pagos (moment, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $pago_vendedor . "', 'Comercialización', 'aprobado')";
            $object['resultado_abono'] = json_encode($localConnection->goQuery($sql));
            $object['pago a vendedor'] = 'SI hubo comisión, cliente normal';
            /* if ($arg["sales_commission"] === true) {
                                  $object['sales_commission_ISSET'][] = true;
                                  } else {
                                      $object["pago a vendedor"] = "NO hubo comisión, cliente excento";
                                  } */
        }  /*  else {
                 $object['sales_commission_ISSET'][] = false;
                 } */

        // GUARDAR DATOS DE DISEÑO
        $sql_diseno = '';
        if ($newJson['diseno_grafico'] == 'true') {
            for ($i = 0; $i < intval($newJson['diseno_grafico_cantidad']); $i++) {
                $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'gráfico', 0);";
            }
        }

        if ($newJson['diseno_modas'] == 'true') {
            for ($i = 0; $i < intval($newJson['diseno_modas_cantidad']); $i++) {
                $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'modas', 0);";
            }
        }

        $object['miDiseno'] = json_encode($localConnection->goQuery($sql_diseno));

        // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
        $sql = 'SELECT _id';

        for ($i = 0; $i <= $count; $i++) {
            if (isset($misProductos[$i])) {
                // PREPARAR FECHAS
                $myDate = new CustomTime();
                $now = $myDate->today();

                $decodedObj = $misProductos[$i];

                /* $woo = new WooMe();
                        $data_category = $woo->getCategoryById(intval($decodedObj['categoria']));
                        $tmp = json_decode($data_category);
                        $cat_name = $tmp->name; */
                /* if ($tmp->statusCode === 500) {
                            $cat_name = "Uncatagorized";
                            } else {
                            } */

                $cat_name = 'Uncatagorized';

                $values = "'" . $now . "',";
                $values .= $decodedObj['precio'] . ',';
                $values .= "'" . $decodedObj['precioWoo'] . "',";
                $values .= "'" . $decodedObj['producto'] . "',";
                $values .= $last_id . ',';
                $values .= $decodedObj['cod'] . ',';
                $values .= $decodedObj['cantidad'] . ',';
                $values .= $decodedObj['categoria'] . ',';
                $values .= "'" . $cat_name . "',";
                // $values .= "'" . $tmp["->name"] . "',";

                if (isset($decodedObj['talla'])) {
                    $values .= "'" . $decodedObj['talla'] . "',";
                } else {
                    $values .= "'',";
                }

                if (isset($decodedObj['corte'])) {
                    $values .= "'" . $decodedObj['corte'] . "',";
                } else {
                    $values .= "'',";
                }

                if (isset($decodedObj['tela'])) {
                    $values .= "'" . $decodedObj['tela'] . "'";
                } else {
                    $values .= "''";
                }

                $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela) VALUES (' . $values . ')';
                $object['sql_ordenes_productos'] = $sql2;
                $object['producto_detalle'][] = $localConnection->goQuery($sql2);

                // BUSCAR EMPLEADOS Y GUARDARLOS EN UN VECTOR PARA ASIGANR A CASDA UNO ...
                if ($misProductos[$i] != '') {
                    $sql_order = 'SELECT * FROM ordenes WHERE _id = ' . $last_id;
                    $myOrder = $localConnection->goQuery($sql_order);
                    $object['myOrder_sql'] = $sql_order;
                    $object['myOrder'] = $myOrder;

                    // Obtenr ultimo ID del producto creado
                    $last_prod = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes_productos');
                    $last_id_ordenes_productos = intval($last_prod[0]['id']);

                    // PREPARAR FECHAS
                    $myDate = new CustomTime();
                    $now = $myDate->today();

                    // FILTRAR DISEñOS POR `id_woo` PARA EVITAR INCUIRLOS COMO PRODUCTOS EN EL LOTE PORQUE EL CONTROL DE DISEÑOS DE LLEVA EN LA TABLA `disenos`
                    $myWooId = intval($decodedObj['cod']);
                    if ($myWooId != 11 && $myWooId != 12 && $myWooId != 13 && $myWooId != 14 && $myWooId != 15 && $myWooId != 16 && $myWooId != 112 && $myWooId != 113 && $myWooId != 168 && $myWooId != 169 && $myWooId != 170 && $myWooId != 171 && $myWooId != 172 && $myWooId != 173) {
                        $sql_lote_detalles = '';
                        // $sql_lote_detalles = "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Responsable');";
                        // $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Diseño');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Corte');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Impresión');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Estampado');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Costura');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Limpieza');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Revisión');";
                        $object['sql_lotes_detalles'][$i] = $sql_lote_detalles;
                        $object['lote_detalles'][$i] = $localConnection->goQuery($sql_lote_detalles);
                    }
                }
            }
        }

        // GUARDAR LOTE

        // -> VERIFICAR SI LA ORDEN ES SOLO DE DISEÑO NO CREAR EL LOTE
        $sql_verify = 'SELECT category_name FROM ordenes_productos WHERE id_orden = ' . $last_id;
        $resultVerify = $localConnection->goQuery($sql_verify);

        $guardarLote = true;
        if (!empty($resultVerify)) {
            // if (count($resultVerify) === 1 && substr($resultVerify["category_name"], 0, strlen("Diseños")) === "Diseños") {
            if (count($resultVerify) === 1 && $resultVerify[0]['category_name'] === 'Diseños') {
                $guardarLote = false;
            }
        }

        $object['guardar_en_lote'] = $guardarLote;

        if ($guardarLote) {
            $sql_lote = "INSERT INTO lotes (moment, fecha, id_orden, lote, paso) VALUES ('" . $now . "', '" . date('Y-m-d') . "', " . $last_id . ', ' . $last_id . ", 'producción')";
            $object['miLote'] = json_encode($localConnection->goQuery($sql_lote));
        }

        // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
        $sql_metodos_pago = '';

        if (intval($arr['montoDolaresEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '1', 'Nueva Orden');";
            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoDolaresEfectivo'] . "', 'Dólares', 1, 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoDolaresZelle']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '1', 'Nueva Orden');";
        }

        if (intval($arr['montoDolaresPanama']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '1', 'Nueva Orden');";
        }

        if (intval($arr['montoPesosEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoPesosEfectivo'] . "', 'Pesos', '" . $arr['tasa_peso'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoPesosTransferencia']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";

            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $arr['tasa_dolar'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresPunto']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresPagomovil']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresTransferencia']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
        }

        $object['metodos_pago'][$i] = $localConnection->goQuery($sql_metodos_pago);

        // enviar email - obtener formato
        $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
        $object['resultBuscar'] = $resultBuscar['object'];
        /* $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
            $object["sendMail"] = $result; */

        $response->getBody()->write(json_encode($object));

        $localConnection->disconnect();

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // CREAR NUEVO PRESUPUESTO
    $app->post('/presupuesto/nuevo', function (Request $request, Response $response, $arg) {
        $newJson = $request->getParsedBody();
        $misProductos = json_decode($newJson['productos'], true);
        $localConnection = new LocalDB();

        // $misProductosLotesDealles = json_decode($newJson['productos_lotes_detalles'], true);
        $count = count($misProductos);

        $arr['id_wp'] = json_decode($newJson['id']);
        $arr['nombre'] = json_decode($newJson['nombre']);
        $arr['vinculada'] = json_decode($newJson['vinculada']);
        $arr['apellido'] = json_decode($newJson['apellido']);
        $arr['cedula'] = json_decode($newJson['cedula']);
        $arr['telefono'] = json_decode($newJson['telefono']);
        $arr['email'] = json_decode($newJson['email']);
        $arr['direccion'] = json_decode($newJson['direccion']);
        $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
        $arr['misProductos'] = json_decode($newJson['productos'], true);
        $arr['obs'] = json_decode($newJson['obs']);
        $arr['total'] = json_decode($newJson['total']);
        $arr['abono'] = json_decode($newJson['abono']);
        $arr['descuento'] = json_decode($newJson['descuento']);
        $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
        $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
        $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
        $arr['responsable'] = json_decode($newJson['responsable']);
        $arr['sales_commission'] = json_decode($newJson['sales_commission']);

        // RECIBIR LOS METODOS DE PAGO
        $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
        $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
        $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
        $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
        $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
        $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
        $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
        $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
        $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
        $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
        $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
        $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
        $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
        $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
        $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
        $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
        $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
        $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
        $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
        $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);

        $arr['hoy'] = date('d/m/Y');
        // $object["arr"] = $arr;
        $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        // Crear nueva orden en Woocommerce
        $orderWC = 0;
        /* $woo = new WooMe();
            $orderWC = $woo->createOrder($arr, $newJson); */
        // $object["create_product_WC"] = $orderWC;
        /* $response->getBody()->write(json_encode($object));
            return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); */
        /* *** */

        /* Enviar email al cliente */
        // $woo = new WooMe();
        // Por ejemplo:
        // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

        /* Craer orden en nunesys */
        $sql = 'INSERT INTO presupuestos (id_wp_order, responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status ) VALUES (' . $orderWC . ', ' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', '" . addslashes($newJson['obs']) . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'En espera' )";

        $object['nuevo_presupuesto_response'] = json_encode($localConnection->goQuery($sql));

        // Obtenr id de la orden creada
        $last = $localConnection->goQuery('SELECT MAX(_id) id FROM presupuestos');
        $last_id = intval($last[0]['id']);
        $object['last_id'] = $last_id;

        // Guardar orden vinculada
        /* if ($arr["vinculada"] != 0 || $arr["vinculada"] != '0') {
                $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr["vinculada"] . ", " . $last_id . ")";
                $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
            } */

        // Crear abono inicial de la orden

        /*
         * $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson["abono"] . "', '" . $newJson['descuento'] . "');";
         *  $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));
         */
        // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
        // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono

        // GUARDAR DATOS DE DISEÑO
        /*  $sql_diseno = "";
             if ($newJson["diseno_grafico"] == "true") {
                 for ($i = 0; $i < intval($newJson["diseno_grafico_cantidad"]); $i++) {
                     $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'gráfico', 0);";
                 }
             }

             if ($newJson["diseno_modas"] == "true") {
                 for ($i = 0; $i < intval($newJson["diseno_modas_cantidad"]); $i++) {
                     $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'modas', 0);";
                 }
             }

             $object['miDiseno'] = json_encode($localConnection->goQuery($sql_diseno)); */

        // GUARDAR PRODUCTOS ASOCIADOS AL PRESUPUESTO
        $sql = 'SELECT _id';

        for ($i = 0; $i <= $count; $i++) {
            if (isset($misProductos[$i])) {
                // PREPARAR FECHAS
                $myDate = new CustomTime();
                $now = $myDate->today();

                $decodedObj = $misProductos[$i];

                $cat_name = 'Uncatagorized';

                $values = "'" . $now . "',";
                $values .= $decodedObj['precio'] . ',';
                $values .= "'" . $decodedObj['precioWoo'] . "',";
                $values .= "'" . $decodedObj['producto'] . "',";
                $values .= $last_id . ',';
                $values .= $decodedObj['cod'] . ',';
                $values .= $decodedObj['cantidad'] . ',';
                $values .= $decodedObj['categoria'] . ',';
                $values .= "'" . $cat_name . "',";
                // $values .= "'" . $tmp["->name"] . "',";

                if (isset($decodedObj['talla'])) {
                    $values .= "'" . $decodedObj['talla'] . "',";
                } else {
                    $values .= "'',";
                }

                if (isset($decodedObj['corte'])) {
                    $values .= "'" . $decodedObj['corte'] . "',";
                } else {
                    $values .= "'',";
                }

                if (isset($decodedObj['tela'])) {
                    $values .= "'" . $decodedObj['tela'] . "'";
                } else {
                    $values .= "''";
                }

                $sql2 = 'INSERT INTO presupuestos_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela) VALUES (' . $values . ')';
                $object['sql_presupuestos_productos'] = $sql2;
                $object['producto_detalle'][] = $localConnection->goQuery($sql2);
            }
        }

        // enviar email - obtener formato
        // $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
        // $object["resultBuscar"] = $resultBuscar["object"];
        // $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
        // $object["sendMail"] = $result;

        $response->getBody()->write(json_encode($object));

        $localConnection->disconnect();

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // CREAR NUEVA ORDEN ANTES DE CUSTOM
    $app->post('/ordenes/nueva/custom', function (Request $request, Response $response, $arg) {
        $newJson = $request->getParsedBody();
        $misProductos = json_decode($newJson['productos'], true);
        $localConnection = new LocalDB();

        $count = count($misProductos);

        $arr['id_wp'] = json_decode($newJson['id']);
        $arr['nombre'] = json_decode($newJson['nombre']);
        $arr['vinculada'] = json_decode($newJson['vinculada']);
        $arr['apellido'] = json_decode($newJson['apellido']);
        $arr['cedula'] = json_decode($newJson['cedula']);
        $arr['telefono'] = json_decode($newJson['telefono']);
        $arr['email'] = json_decode($newJson['email']);
        $arr['direccion'] = json_decode($newJson['direccion']);
        $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
        $arr['misProductos'] = json_decode($newJson['productos'], true);
        $arr['obs'] = json_decode($newJson['obs']);
        $arr['total'] = json_decode($newJson['total']);
        $arr['abono'] = json_decode($newJson['abono']);
        $arr['descuento'] = json_decode($newJson['descuento']);
        $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
        $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
        $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
        $arr['responsable'] = json_decode($newJson['responsable']);
        $arr['sales_commission'] = json_decode($newJson['sales_commission']);

        // RECIBIR LOS METODOS DE PAGO
        $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
        $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
        $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
        $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
        $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
        $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
        $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
        $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
        $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
        $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
        $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
        $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
        $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
        $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
        $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
        $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
        $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
        $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
        $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
        $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);

        $arr['hoy'] = date('d/m/Y');
        // $object["arr"] = $arr;
        $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        // Crear nueva orden en Woocommerce
        $orderWC = 0;
        /* $woo = new WooMe();
            $orderWC = $woo->createOrder($arr, $newJson);
            $object["create_product_WC"] = $orderWC;*/
        /* $response->getBody()->write(json_encode($object));
            return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); */
        /* *** */

        /* Enviar email al cliente */
        // $woo = new WooMe();
        // Por ejemplo:
        // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

        /* DEBuG */
        $object['newJson'] = $newJson;

        /* Craer orden en nunesys */
        $sql = 'INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status ) VALUES (' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', '" . addslashes($newJson['obs']) . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'En espera' )";
        $object['nueva_oreden_sql'] = $sql;
        $object['nueva_oreden_response'] = json_encode($localConnection->goQuery($sql));

        // Obtenr id de la orden creada
        $last = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes');
        $last_id = intval($last[0]['id']);
        $object['last_id'] = $last_id;

        // Guardar orden vinculada
        if ($arr['vinculada'] != 0 || $arr['vinculada'] != '0') {
            $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr['vinculada'] . ', ' . $last_id . ')';
            $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
        }

        // Crear abono inicial de la orden
        $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson['abono'] . "', '" . $newJson['descuento'] . "');";
        $object['sql_abonos'] = $sql;
        $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));

        // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
        // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono
        if (floatval($newJson['abono']) > 0) {
            // $object['sales_commission_ISSET'][] = $arg["sales_commission"];
            $pago_vendedor = floatval($newJson['abono']) * 5 / 100;
            $pago_vendedor = number_format($pago_vendedor, 2);
            $sql = "INSERT INTO pagos (moment, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $pago_vendedor . "', 'Comercialización', 'aprobado')";
            $object['resultado_abono'] = json_encode($localConnection->goQuery($sql));
            $object['pago a vendedor'] = 'SI hubo comisión, cliente normal';
            /* if ($arg["sales_commission"] === true) {
                      $object['sales_commission_ISSET'][] = true;
                      } else {
                          $object["pago a vendedor"] = "NO hubo comisión, cliente excento";
                      } */
        }  /*  else {
                     $object['sales_commission_ISSET'][] = false;
                 } */

        // GUARDAR DATOS DE DISEÑO
        $sql_diseno = '';
        if ($newJson['diseno_grafico'] == true) {
            for ($i = 0; $i < intval($newJson['diseno_grafico_cantidad']); $i++) {
                $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'gráfico', 0);";
            }
        }

        if ($newJson['diseno_modas'] == 'true') {
            for ($i = 0; $i < intval($newJson['diseno_modas_cantidad']); $i++) {
                $sql_diseno .= "INSERT INTO disenos (moment, id_orden, tipo, id_empleado) VALUES ('" . $now . "', " . $last_id . ", 'modas', 0);";
            }
        }

        $object['miDiseno'] = json_encode($localConnection->goQuery($sql_diseno));

        // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
        $sql = 'SELECT _id';

        for ($i = 0; $i <= $count; $i++) {
            if (isset($misProductos[$i])) {
                // PREPARAR FECHAS
                $myDate = new CustomTime();
                $now = $myDate->today();

                $decodedObj = $misProductos[$i];

                /* $woo = new WooMe();
                        $data_category = $woo->getCategoryById(intval($decodedObj['categoria']));
                        $tmp = json_decode($data_category);
                        $cat_name = $tmp->name; */
                /* if ($tmp->statusCode === 500) {
                            $cat_name = "Uncatagorized";
                            } else {
                            } */
                $sqlc = 'SELECT `nombre` FROM `categories` WHERE  _id = ' . $decodedObj['categoria'];
                $cat_name_base = $localConnection->goQuery($sqlc);
                $cat_name = $cat_name_base[0]['nombre'];
                // $cat_name = "Uncatagorized";

                $values = "'" . $now . "',";
                $values .= $decodedObj['precio'] . ',';
                $values .= "'" . $decodedObj['precioWoo'] . "',";
                $values .= "'" . $decodedObj['producto'] . "',";
                $values .= $last_id . ',';
                $values .= $decodedObj['cod'] . ',';
                $values .= $decodedObj['cantidad'] . ',';
                $values .= $decodedObj['categoria'] . ',';
                $values .= "'" . $cat_name . "',";
                // $values .= "'" . $tmp["->name"] . "',";

                if (isset($decodedObj['talla'])) {
                    $values .= "'" . $decodedObj['talla'] . "',";
                } else {
                    $values .= "'',";
                }

                if (isset($decodedObj['corte'])) {
                    $values .= "'" . $decodedObj['corte'] . "',";
                } else {
                    $values .= "'',";
                }

                if (isset($decodedObj['tela'])) {
                    $values .= "'" . $decodedObj['tela'] . "'";
                } else {
                    $values .= "''";
                }

                $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela) VALUES (' . $values . ')';
                $object['sql_ordenes_productos'] = $sql2;
                $object['producto_detalle'][] = $localConnection->goQuery($sql2);

                // BUSCAR EMPLEADOS Y GUARDARLOS EN UN VECTOR PARA ASIGANR A CASDA UNO ...
                if ($misProductos[$i] != '') {
                    $sql_order = 'SELECT * FROM ordenes WHERE _id = ' . $last_id;
                    $myOrder = $localConnection->goQuery($sql_order);
                    $object['myOrder_sql'] = $sql_order;
                    $object['myOrder'] = $myOrder;

                    // Obtenr ultimo ID del producto creado
                    $last_prod = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes_productos');
                    $last_id_ordenes_productos = intval($last_prod[0]['id']);

                    // PREPARAR FECHAS
                    $myDate = new CustomTime();
                    $now = $myDate->today();

                    // FILTRAR DISEñOS POR `id_woo` PARA EVITAR INCUIRLOS COMO PRODUCTOS EN EL LOTE PORQUE EL CONTROL DE DISEÑOS DE LLEVA EN LA TABLA `disenos`
                    $myWooId = intval($decodedObj['cod']);
                    if ($myWooId != 11 && $myWooId != 12 && $myWooId != 13 && $myWooId != 14 && $myWooId != 15 && $myWooId != 16 && $myWooId != 112 && $myWooId != 113 && $myWooId != 168 && $myWooId != 169 && $myWooId != 170 && $myWooId != 171 && $myWooId != 172 && $myWooId != 173) {
                        $sql_lote_detalles = '';
                        // $sql_lote_detalles = "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Responsable');";
                        // $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Diseño');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Corte');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Impresión');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Estampado');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Costura');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Limpieza');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Revisión');";
                        $object['sql_lotes_detalles'][$i] = $sql_lote_detalles;
                        $object['lote_detalles'][$i] = $localConnection->goQuery($sql_lote_detalles);
                    }
                }
            }
        }

        // GUARDAR LOTE

        // -> VERIFICAR SI LA ORDEN ES SOLO DE DISEÑO NO CREAR EL LOTE
        $sql_verify = 'SELECT category_name FROM ordenes_productos WHERE id_orden = ' . $last_id;
        $resultVerify = $localConnection->goQuery($sql_verify);

        $guardarLote = true;
        if (!empty($resultVerify)) {
            // if (count($resultVerify) === 1 && substr($resultVerify["category_name"], 0, strlen("Diseños")) === "Diseños") {
            if (count($resultVerify) === 1 && $resultVerify[0]['category_name'] === 'Diseños') {
                $guardarLote = false;
            }
        }

        $object['guardar_en_lote'] = $guardarLote;

        if ($guardarLote) {
            $sql_lote = "INSERT INTO lotes (moment, fecha, id_orden, lote, paso) VALUES ('" . $now . "', '" . date('Y-m-d') . "', " . $last_id . ', ' . $last_id . ", 'producción')";
            $object['miLote'] = json_encode($localConnection->goQuery($sql_lote));
        }

        // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
        $sql_metodos_pago = '';

        if (intval($arr['montoDolaresEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '1', 'Nueva Orden');";
            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoDolaresEfectivo'] . "', 'Dólares', 1, 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoDolaresZelle']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '1', 'Nueva Orden');";
        }

        if (intval($arr['montoDolaresPanama']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '1', 'Nueva Orden');";
        }

        if (intval($arr['montoPesosEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoPesosEfectivo'] . "', 'Pesos', '" . $arr['tasa_peso'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoPesosTransferencia']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";

            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $arr['tasa_dolar'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresPunto']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresPagomovil']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresTransferencia']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
        }
        $object['sql_metodos_pago'] = $sql_metodos_pago;

        if ($sql_metodos_pago != '') {
            $object['metodos_pago'][$i] = $localConnection->goQuery($sql_metodos_pago);
        }

        // enviar email - obtener formato
        // $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
        // $object["resultBuscar"] = $resultBuscar["object"];
        /* $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
            $object["sendMail"] = $result; */

        $response->getBody()->write(json_encode($object));

        $localConnection->disconnect();

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // CREAR NUEVA ORDEN ANTES DE SPORT
    $app->post('/ordenes/nueva/sport', function (Request $request, Response $response, $arg) {
        $newJson = $request->getParsedBody();
        $misProductos = json_decode($newJson['productos'], true);
        $localConnection = new LocalDB();

        // $misProductosLotesDealles = json_decode($newJson['productos_lotes_detalles'], true);
        $count = count($misProductos);

        $arr['id_wp'] = json_decode($newJson['id']);
        $arr['nombre'] = json_decode($newJson['nombre']);
        $arr['vinculada'] = json_decode($newJson['vinculada']);
        $arr['apellido'] = json_decode($newJson['apellido']);
        $arr['cedula'] = json_decode($newJson['cedula']);
        $arr['telefono'] = json_decode($newJson['telefono']);
        $arr['email'] = json_decode($newJson['email']);
        $arr['direccion'] = json_decode($newJson['direccion']);
        $arr['fechaEntrega'] = json_decode($newJson['fechaEntrega']);
        $arr['misProductos'] = json_decode($newJson['productos'], true);
        $arr['obs'] = json_decode($newJson['obs']);
        $arr['total'] = json_decode($newJson['total']);
        $arr['abono'] = json_decode($newJson['abono']);
        $arr['descuento'] = json_decode($newJson['descuento']);
        $arr['descuentoDetalle'] = json_decode($newJson['descuentoDetalle']);
        $arr['diseno_grafico'] = json_decode($newJson['diseno_grafico']);
        $arr['diseno_modas'] = json_decode($newJson['diseno_modas']);
        $arr['responsable'] = json_decode($newJson['responsable']);
        $arr['sales_commission'] = json_decode($newJson['sales_commission']);

        // RECIBIR LOS METODOS DE PAGO
        $arr['montoDolaresEfectivo'] = json_decode($newJson['montoDolaresEfectivo']);
        $arr['montoDolaresEfectivoDetalle'] = json_decode($newJson['montoDolaresEfectivoDetalle']);
        $arr['montoDolaresZelle'] = json_decode($newJson['montoDolaresZelle']);
        $arr['montoDolaresZelleDetalle'] = json_decode($newJson['montoDolaresZelleDetalle']);
        $arr['montoDolaresPanama'] = json_decode($newJson['montoDolaresPanama']);
        $arr['montoDolaresPanamaDetalle'] = json_decode($newJson['montoDolaresPanamaDetalle']);
        $arr['montoPesosEfectivo'] = json_decode($newJson['montoPesosEfectivo']);
        $arr['montoPesosEfectivoDetalle'] = json_decode($newJson['montoPesosEfectivoDetalle']);
        $arr['montoPesosTransferencia'] = json_decode($newJson['montoPesosTransferencia']);
        $arr['montoPesosTransferenciaDetalle'] = json_decode($newJson['montoPesosTransferenciaDetalle']);
        $arr['montoBolivaresEfectivo'] = json_decode($newJson['montoBolivaresEfectivo']);
        $arr['montoBolivaresEfectivoDetalle'] = json_decode($newJson['montoBolivaresEfectivoDetalle']);
        $arr['montoBolivaresPunto'] = json_decode($newJson['montoBolivaresPunto']);
        $arr['montoBolivaresPuntoDetalle'] = json_decode($newJson['montoBolivaresPuntoDetalle']);
        $arr['montoBolivaresPagomovil'] = json_decode($newJson['montoBolivaresPagomovil']);
        $arr['montoBolivaresPagomovilDetalle'] = json_decode($newJson['montoBolivaresPagomovilDetalle']);
        $arr['montoBolivaresTransferencia'] = json_decode($newJson['montoBolivaresTransferencia']);
        $arr['montoBolivaresTransferenciaDetalle'] = json_decode($newJson['montoBolivaresTransferenciaDetalle']);
        $arr['tasa_dolar'] = json_decode($newJson['tasa_dolar']);
        $arr['tasa_peso'] = json_decode($newJson['tasa_peso']);

        $arr['hoy'] = date('d/m/Y');
        // $object["arr"] = $arr;
        $cliente = $newJson['nombre'] . ' ' . $newJson['apellido'];

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        // Crear nueva orden en Woocommerce
        $orderWC = 0;
        /* $woo = new WooMe();
            $orderWC = $woo->createOrder($arr, $newJson);
            $object["create_product_WC"] = $orderWC;*/
        /* $response->getBody()->write(json_encode($object));
            return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200); */
        /* *** */

        /* Enviar email al cliente */
        // $woo = new WooMe();
        // Por ejemplo:
        // $woo->sendMail($orderWC->id, 'Mensaje de confirmacion de cracion de orden para el cliente'); // Reemplaza "enviarCorreoElectronico" con la función real

        /* Craer orden en nunesys */
        $sql = 'INSERT INTO ordenes (responsable, moment, pago_descuento, pago_abono, id_wp, cliente_cedula, observaciones, pago_total, cliente_nombre, fecha_inicio, fecha_entrega, fecha_creacion, status, tipo ) VALUES (' . $newJson['responsable'] . ", '" . $now . "', " . $arr['descuento'] . ', ' . $arr['abono'] . ",  '" . $arr['id_wp'] . "', '" . $arr['cedula'] . "', '" . addslashes($newJson['obs']) . "', " . $newJson['total'] . ",' " . $cliente . "', '" . date('Y-m-d') . "', '" . $newJson['fechaEntrega'] . "', '" . date('Y-m-d') . "', 'entregada', 'sport')";

        $object['nueva_oreden_response'] = json_encode($localConnection->goQuery($sql));

        // Obtenr id de la orden creada
        $last = $localConnection->goQuery('SELECT MAX(_id) id FROM ordenes');
        $last_id = intval($last[0]['id']);
        $object['last_id'] = $last_id;

        // Guardar orden vinculada
        if ($arr['vinculada'] != 0 || $arr['vinculada'] != '0') {
            $sql = "INSERT INTO ordenes_vinculadas (moment, id_father, id_child) VALUES ('" . $now . "', " . $arr['vinculada'] . ', ' . $last_id . ')';
            $object['response_orden_vinculada'] = json_encode($localConnection->goQuery($sql));
        }

        // Crear abono inicial de la orden
        $sql = "INSERT INTO abonos (moment, id_orden, id_empleado, abono, descuento) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $newJson['abono'] . "', '" . $newJson['descuento'] . "');";
        $object['response_primer_abono'] = json_encode($localConnection->goQuery($sql));

        // CALCULAMOE ES PORCENTAJE DEL VENDEDOR
        // if (isset($arg["sales_commission"])) { // sales_comission no llega en el Payload vamoa a validar el valor de abono
        if (floatval($newJson['abono']) > 0) {
            // $object['sales_commission_ISSET'][] = $arg["sales_commission"];
            $pago_vendedor = floatval($newJson['abono']) * 5 / 100;
            $pago_vendedor = number_format($pago_vendedor, 2);
            $sql = "INSERT INTO pagos (moment, id_orden, id_empleado, monto_pago, detalle, estatus) VALUES ('" . $now . "', '" . $last_id . "',  '" . $newJson['responsable'] . "', '" . $pago_vendedor . "', 'Comercialización', 'aprobado')";
            $object['resultado_abono'] = json_encode($localConnection->goQuery($sql));
            $object['pago a vendedor'] = 'SI hubo comisión, cliente normal';
            /* if ($arg["sales_commission"] === true) {
                      $object['sales_commission_ISSET'][] = true;
                      } else {
                          $object["pago a vendedor"] = "NO hubo comisión, cliente excento";
                      } */
        }  /*  else {
$object['sales_commission_ISSET'][] = false;
} */

        // GUARDAR PRODUCTOS ASOCIADOS A LA ORDEN
        $sql = 'SELECT _id';

        for ($i = 0; $i <= $count; $i++) {
            if (isset($misProductos[$i])) {
                // PREPARAR FECHAS
                $myDate = new CustomTime();
                $now = $myDate->today();

                $decodedObj = $misProductos[$i];

                $cat_name = 'Uncatagorized';

                $values = "'" . $now . "',";
                $values .= $decodedObj['precio'] . ',';
                $values .= "'" . $decodedObj['precioWoo'] . "',";
                $values .= "'" . $decodedObj['producto'] . "',";
                $values .= $last_id . ',';
                $values .= $decodedObj['cod'] . ',';
                $values .= $decodedObj['cantidad'] . ',';
                $values .= $decodedObj['categoria'] . ',';
                $values .= "'" . $cat_name . "',";
                // $values .= "'" . $tmp["->name"] . "',";

                if (isset($decodedObj['talla'])) {
                    $values .= "'" . $decodedObj['talla'] . "',";
                } else {
                    $values .= "'',";
                }

                if (isset($decodedObj['corte'])) {
                    $values .= "'" . $decodedObj['corte'] . "',";
                } else {
                    $values .= "'',";
                }

                if (isset($decodedObj['tela'])) {
                    $values .= "'" . $decodedObj['tela'] . "'";
                } else {
                    $values .= "''";
                }

                $sql2 = 'INSERT INTO ordenes_productos (moment, precio_unitario, precio_woo, name, id_orden, id_woo, cantidad, id_category, category_name, talla, corte, tela) VALUES (' . $values . ')';
                $object['sql_ordenes_productos'] = $sql2;
                $object['producto_detalle'][] = $localConnection->goQuery($sql2);

                // BUSCAR EMPLEADOS Y GUARDARLOS EN UN VECTOR PARA ASIGANR A CASDA UNO ...
                /* if ($misProductos[$i] != '') {
                    $sql_order = "SELECT * FROM ordenes WHERE _id = " . $last_id;
                    $myOrder = $localConnection->goQuery($sql_order);
                    $object['myOrder_sql'] = $sql_order;
                    $object['myOrder'] = $myOrder;

// Obtenr ultimo ID del producto creado
                    $last_prod = $localConnection->goQuery("SELECT MAX(_id) id FROM ordenes_productos");
                    $last_id_ordenes_productos = intval($last_prod[0]['id']);

// PREPARAR FECHAS
                    $myDate = new CustomTime();
                    $now = $myDate->today();

// FILTRAR DISEñOS POR `id_woo` PARA EVITAR INCUIRLOS COMO PRODUCTOS EN EL LOTE PORQUE EL CONTROL DE DISEÑOS DE LLEVA EN LA TABLA `disenos`
                    $myWooId = intval($decodedObj['cod']);
                    if ($myWooId != 11 && $myWooId != 12 && $myWooId != 13 && $myWooId != 14 && $myWooId != 15 && $myWooId != 16 && $myWooId != 112 && $myWooId != 113 && $myWooId != 168 && $myWooId != 169 && $myWooId != 170 && $myWooId != 171 && $myWooId != 172 && $myWooId != 173) {
                        $sql_lote_detalles = "";
// $sql_lote_detalles = "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Responsable');";
// $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Diseño');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Corte');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Impresión');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Estampado');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Costura');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Limpieza');";
                        $sql_lote_detalles .= "INSERT INTO lotes_detalles (`moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`) VALUES ( '" . $now . "', '" . $last_id . "', '" . $last_id_ordenes_productos . "', '" . $decodedObj['cod'] . "', 'Revisión');";
                        $object['sql_lotes_detalles'][$i] = $sql_lote_detalles;
                        $object['lote_detalles'][$i] = $localConnection->goQuery($sql_lote_detalles);
                    }
                } */
            }
        }

        // GUARDAR LOTE

        /* // -> VERIFICAR SI LA ORDEN ES SOLO DE DISEÑO NO CREAR EL LOTE
        $sql_verify = "SELECT category_name FROM ordenes_productos WHERE id_orden = " . $last_id;
        $resultVerify = $localConnection->goQuery($sql_verify);

        $guardarLote = true;
        if (!empty($resultVerify)) {
// if (count($resultVerify) === 1 && substr($resultVerify["category_name"], 0, strlen("Diseños")) === "Diseños") {
            if (count($resultVerify) === 1 && $resultVerify[0]["category_name"] === "Diseños") {
                $guardarLote = false;
            }
        }

        $object["guardar_en_lote"] = $guardarLote;

        if ($guardarLote) {
            $sql_lote = "INSERT INTO lotes (moment, fecha, id_orden, lote, paso) VALUES ('" . $now . "', '" . date("Y-m-d") . "', " . $last_id . ", " . $last_id . ", 'producción')";
            $object['miLote'] = json_encode($localConnection->goQuery($sql_lote));
        } */

        // GUARDAR METODOS DE PAGO UTILIZADOS EN LA ORDEN
        $sql_metodos_pago = '';

        if (intval($arr['montoDolaresEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Efectivo', '" . $arr['montoDolaresEfectivo'] . "', '1', 'Nueva Orden');";
            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoDolaresEfectivo'] . "', 'Dólares', 1, 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoDolaresZelle']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Zelle', '" . $arr['montoDolaresZelle'] . "', '1', 'Nueva Orden');";
        }

        if (intval($arr['montoDolaresPanama']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Dólares', 'Panamá', '" . $arr['montoDolaresPanama'] . "', '1', 'Nueva Orden');";
        }

        if (intval($arr['montoPesosEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Efectivo', '" . $arr['montoPesosEfectivo'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoPesosEfectivo'] . "', 'Pesos', '" . $arr['tasa_peso'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoPesosTransferencia']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Pesos', 'Transferencia', '" . $arr['montoPesosTransferencia'] . "', '" . $arr['tasa_peso'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresEfectivo']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Efectivo', '" . $arr['montoBolivaresEfectivo'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";

            $sql_metodos_pago .= "INSERT INTO caja (monto, moneda, tasa, tipo, id_empleado, detalle) VALUES ('" . $arr['montoBolivaresEfectivo'] . "', 'Bolívares', '" . $arr['tasa_dolar'] . "', 'orden_nueva', '" . $newJson['responsable'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresPunto']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Punto', '" . $arr['montoBolivaresPunto'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresPagomovil']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Pagomovil', '" . $arr['montoBolivaresPagomovil'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
        }

        if (intval($arr['montoBolivaresTransferencia']) > 0) {
            $sql_metodos_pago .= "INSERT INTO metodos_de_pago (id_orden, moneda, metodo_pago, monto, tasa, detalle) VALUES ('" . $last_id . "', 'Bolívares', 'Transferencia', '" . $arr['montoBolivaresTransferencia'] . "', '" . $arr['tasa_dolar'] . "', 'Nueva Orden');";
        }

        $object['metodos_pago'][$i] = $localConnection->goQuery($sql_metodos_pago);

        // enviar email - obtener formato
        $resultBuscar = obtenerRespuestaBuscar($last_id, 'true');
        $object['resultBuscar'] = $resultBuscar['object'];
        /*  $result = $woo->sendMail($orderWC->id, $resultBuscar["object"]);
            $object["sendMail"] = $result; */

        $response->getBody()->write(json_encode($object));

        $localConnection->disconnect();

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // FIN CREAR NUEVA ORDEN

    // CAMBIAR ESTATUS DE LA REVISIÓN
    $app->post('/comercializacion/revisiones-estatus/{estatus}/{id_revision}/{id_orden}', function (Request $request, Response $response, array $args) {
        $localConnection = new localDB();

        $sql = "UPDATE revisiones SET estatus = '" . $args['estatus'] . "' WHERE _id = " . $args['id_revision'];
        $object['revisiones'] = $localConnection->goQuery($sql);

        // BUSCAR EL ID DE LA ORDEN EN `revisiones`
        // $sql = "SELECT id_orden FROM revisiones WHERE _id = " . $args["id_revision"];
        // $miRevision = $localConnection->goQuery($sql);
        $miRevision = $args['id_revision'];

        // CON EL ID DE LA ORDEN BUSCAMOS EL ID DEL DISEÑADOR EN `disenos` `revisiones`
        $sql = 'SELECT id_orden, id_empleado FROM disenos WHERE id_orden = ' . $args['id_orden'];
        $miDiseno = $localConnection->goQuery($sql);

        // VERIFICAR PAGO EXISTENTE
        $sqlPago = "SELECT count(_id) exist FROM pagos WHERE detalle = 'Diseño' AND id_orden = " . $miDiseno[0]['id_orden'] . ' AND id_empleado = ' . $miDiseno[0]['id_empleado'];
        $object['pago_exist'] = $localConnection->goQuery($sqlPago)[0];

        // ELIMINAR PAGO A DISEñADOR POR RECHAZO DE PROPUESTA
        if ($args['estatus'] === 'Rechazado') {
            $estatusTerminado = 0;
            $sql = 'DELETE from pagos WHERE id_empleado = ' . $miDiseno[0]['id_empleado'] . ' AND id_orden = ' . $miDiseno[0]['id_orden'];
            $deleteResult = $localConnection->goQuery($sql);
        }

        // GENERAR PAGO A DISEÑAODRES
        if ($args['estatus'] === 'Aprobado') {
            $estatusTerminado = 1;
            $sql = 'UPDATE disenos SET terminado = ' . $estatusTerminado . ' WHERE id_orden = ' . $miDiseno[0]['id_orden'] . ';';
            $sql .= "UPDATE ordenes SET status = 'activa' WHERE _id = " . $args['id_orden'] . ';';
            $miRevision = $localConnection->goQuery($sql);
            $object['sql_revision'] = $sql;

            // Buscar el id_woo
            $sqlwoo = 'SELECT id_woo FROM ordenes_productos WHERE id_category = 17 AND id_orden = ' . $miDiseno[0]['id_orden'];
            $idWoo = $localConnection->goQuery($sqlwoo);

            if (empty($idWoo)) {
                $object['woo-comision'] = 0;
                $comision = 0;
            } else {
                // Verificar si el pago existe
                $sql = "SELECT _id FROM pagos WHERE detalle = 'Diseño' AND id_empleado = " . $miDiseno[0]['id_empleado'] . ' AND id_orden = ' . $args['id_orden'];
                $object['sql_pago_exist'] = $sql;
                $miPago = $localConnection->goQuery($sql);
                $object['id_woo'] = $idWoo[0]['id_woo'];
                // Buscar en WooMe la comision asociada a el producto $idWoo
                $woo = new WooMe();
                $woomeResponse = $woo->getProductById($idWoo[0]['id_woo']);

                // $object["woo-response"] = json_encode($woomeResponse);
                if (isset($woomeResponse->attributes[0]->options[0])) {
                    $object['woo-comision'] = json_encode($woomeResponse->attributes[0]->options[0]);
                } else {
                    $object['woo-comision'] = 0;
                }

                if (empty($woomeResponse->attributes)) {
                    $comision = 0;
                } else {
                    $comision = $woomeResponse->attributes[0]->options[0];
                }

                if (empty($miPago)) {
                    $sqlPago = 'INSERT INTO pagos (cantidad, id_orden, estatus, monto_pago, id_empleado, detalle) VALUES (1, ' . $args['id_orden'] . ", 'aprobado' , " . $comision . ', ' . $miDiseno[0]['id_empleado'] . ", 'Diseño');";
                    $object['resultInsertPago'] = $localConnection->goQuery($sqlPago);
                } else {
                    // UPDATE pagos
                    $sqlPago = 'UPDATE pagos SET monto_pago = ' . $comision . ' WHERE id_orden = ' . $args['id_orden'] . ' AND id_empleado = ' . $miDiseno[0]['id_empleado'];
                    $object['resultInsertPago'] = $localConnection->goQuery($sqlPago);
                }
                $object['sql_pago'] = $sqlPago;
            }
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // GUARDAR DETALLES DE LA REVISIÓN
    $app->post('/comercializacion/revisiones-detalles/{id_revision}', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $localConnection = new localDB();

        $sql = "UPDATE revisiones SET detalles = '" . htmlspecialchars($data['detalles']) . "' WHERE _id = " . $args['id_revision'];
        $object['revisiones'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // REVISAR REVISIONES PENDIENTES
    $app->get('/comercializacion/revisiones/{id_empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new localDB();

        $sql = 'SELECT acceso FROM  empleados  WHERE _id = ' . $args['id_empleado'];
        $miEmpleado = $localConnection->goQuery($sql);

        if ($miEmpleado[0]['acceso']) {
            // Mostrar todos los registros de revisiones
            $sql = "SELECT a.id_orden, a._id id_revision, a.id_diseno, b.id_wp id_cliente, a.revision, b.cliente_nombre cliente, a.detalles, a.estatus FROM revisiones a JOIN ordenes b ON a.id_orden = b._id WHERE b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado' ORDER BY a._id DESC";
        } else {
            // Mostrar solo los registros del venededor
            $sql = 'SELECT a.id_orden, a._id id_revision, a.id_diseno, b.id_wp id_cliente, a.revision, b.cliente_nombre cliente, a.detalles, a.estatus FROM revisiones a JOIN ordenes b ON a.id_orden = b._id AND b.responsable = ' . $args['id_empleado'] . " WHERE b.responsable = '" . $args['id_empleado'] . "' AND b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado' ORDER BY a._id DESC";
        }

        $object['revisiones'] = $localConnection->goQuery($sql);

        $object['total_revisiones'] = count($object['revisiones']);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** FIN ORDENES */

    /** LOTES */

    // Obtener departamento asignado al empleado
    $app->get('/empleado/asignado/{departamento}/{orden}/{item_id}', function (Request $request, Response $response, array $args) {
        // Verificar la asignacion
        $localConnection = new LocalDB();
        $sql = "SELECT id_empleado FROM lotes_detalles  WHERE id_orden = '" . $args['orden'] . "' AND id_ordenes_productos = '" . $args['item_id'] . "' AND departamento = '" . $args['departamento'] . "'";
        $object['id_empleado'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/lotes/en-proceso', function (Request $request, Response $response, array $args) {
        // BUSCAR ORENES EN CURSO EXCLUYENDO LOS DISEÑOS FILTADOS POR ID DE WOOCOMMERCE
        $localConnection = new LocalDB();
        $sql = "SELECT a._id orden, a._id vinculada, a.cliente_nombre cliente, b.prioridad, b.paso, a.fecha_inicio inicio, a.fecha_entrega entrega, a.observaciones detalles, a._id acciones, a.status estatus FROM ordenes a JOIN lotes b ON a._id = b.id_orden  WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' ORDER BY a._id DESC";
        $object['items'] = $localConnection->goQuery($sql);

        // CREAR CAMPOS DE LA TABLA
        $object['fields'][0]['key'] = 'orden';
        $object['fields'][0]['label'] = 'Orden';

        $object['fields'][1]['key'] = 'cliente';
        $object['fields'][1]['label'] = 'Cliente';

        $object['fields'][2]['key'] = 'prioridad';
        $object['fields'][2]['label'] = 'Prioridad';

        $object['fields'][2]['key'] = 'paso';
        $object['fields'][2]['label'] = 'Progreso';

        $object['fields'][3]['key'] = 'inicio';
        $object['fields'][3]['label'] = 'Inicio';

        $object['fields'][4]['key'] = 'entrega';
        $object['fields'][4]['label'] = 'Entrega';

        $object['fields'][5]['key'] = 'vinculada';
        $object['fields'][5]['label'] = 'Vinculada';

        $object['fields'][6]['key'] = 'estatus';
        $object['fields'][6]['label'] = 'Estatus';

        $object['fields'][7]['key'] = 'detalles';
        $object['fields'][7]['label'] = 'Detalles';

        $object['fields'][8]['key'] = 'acciones';
        $object['fields'][8]['label'] = 'Acciones';

        $go = $object;

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($go));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // VERIFICAR CANTIDAD ASIGNADA EN LOTES
    $app->get('/lotes/verificar-cantidad-asignada/{id_ordenes_productos}/{departamento}/{id_orden}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        // -> VERIFICAR EXISTENCIA DEL REGISTRO
        $sql = "SELECT _id FROM lotes_detalles WHERE id_ordenes_productos  = '" . $args['id_ordenes_productos'] . "' AND departamento = '" . $args['departamento'] . "' AND id_orden = " . $args['id_orden'];
        $object['data'] = $localConnection->goQuery($sql);

        // BUSCAR ORENES EN CURSO EXCLUYENDO LOS DISEÑOS FILTADOS POR ID DE WOOCOMMERCE ???

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER DATOS PARA REPOSICIONES EN EL MODULO DE EMPLEADOS
    $app->get('/empleados/reposicion/{id_orden}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        // EMPLEADOS
        // $sql = "SELECT _id, _id acciones, username, password, nombre, email, departamento, comision, acceso FROM empleados ORDER BY nombre ASC";
        // $object["empleados"] = $localConnection->goQuery($sql);

        // ITEM
        $sql = "SELECT DISTINCT
      a._id AS orden,
      a._id AS vinculada,    
      a.cliente_nombre AS cliente,
      b.prioridad,
      b.paso,
      d.estatus AS estatus_revision,
      a.fecha_inicio AS inicio,
      a.fecha_entrega AS entrega,
      a.observaciones AS detalles,
      a._id AS acciones,
      a.status AS estatus,
      c._id AS id_diseno,
      COALESCE(e.nombre, 'Sin asignar') AS disenador
      FROM
      ordenes a
      JOIN lotes b ON a._id = b.id_orden
      LEFT JOIN disenos c ON a._id = c.id_orden
      LEFT JOIN revisiones d ON d.id_diseno = c._id
      LEFT JOIN empleados e ON e._id = CASE WHEN c.id_empleado = 0 THEN 0 ELSE c.id_empleado END
      WHERE a._id = " . $args['id_orden'] . " AND 
      (a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera')
      AND (SELECT COUNT(_id) FROM lotes_detalles WHERE id_orden = a._id) > 0
      ORDER BY a._id DESC";
        $object['item'] = $localConnection->goQuery($sql);

        // REPOSICION ORDENES PRODUCTOS
        $sql = 'SELECT
        b._id,
        b.id_orden,
        b._id item,
        b.id_woo cod,
        b.name producto,
        b.cantidad,
        b.talla,
        b.tela,
        b.corte,
        b.precio_unitario precio,
        b.precio_woo precioWoo
    FROM
        ordenes_productos b
    JOIN ordenes a ON
        a._id = b.id_orden
    JOIN products p ON p._id = b.id_woo
    WHERE a._id = ' . $args['id_orden'] . " AND 
        (a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera') AND p.fisico = 1";
        $object['reposicion_ordenes_productos'] = $localConnection->goQuery($sql);

        /* // BUSCAR PASO ACTUAL EN EL LOTE
        $sql = "SELECT paso from lotes WHERE _id = " . $args["id_orden"];
        $tmpPaso = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        if (!empty($tmpPaso)) {
          $object["paso"] = $tmpPaso[0]["paso"];
        } else {
          $object["paso"] = null;
        } */

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/lotes/empleados/reasignar', function (Request $request, Response $response, $args) {
        $miEmpleado = $request->getParsedBody();
        $object['parsed_body'] = $miEmpleado;
        $localConnection = new LocalDB();

        $sql = 'SELECT _id, unidades_solicitadas FROM lotes_detalles WHERE id_ordenes_productos  = ' . $miEmpleado['id_ordenes_productos'] . " AND departamento = '" . $miEmpleado['departamento'] . "' AND id_orden = " . $miEmpleado['id_orden'];
        $exist = $localConnection->goQuery($sql);

        $object['count'] = count($exist);

        if ($object['count']) {
            if ($miEmpleado['departamento'] === 'Corte') {
                $nuevaCantiadSolicitada = intval($miEmpleado['cantidad']) + intval($exist[0]['unidades_solicitadas']);

                $values = "id_empleado ='" . $miEmpleado['id_empleado'] . "',";
                $values .= "id_ordenes_productos ='" . $miEmpleado['id_ordenes_productos'] . "',";
                $values .= "unidades_solicitadas ='" . $nuevaCantiadSolicitada . "'";
            } else {
                $values = "id_empleado ='" . $miEmpleado['id_empleado'] . "',";
                $values .= "id_ordenes_productos ='" . $miEmpleado['id_ordenes_productos'] . "',";
                $values .= "unidades_solicitadas ='" . $miEmpleado['cantidad_orden'] . "'";
            }

            $sql = 'UPDATE lotes_detalles SET ' . $values . " WHERE departamento = '" . $miEmpleado['departamento'] . "' AND id_orden = " . $miEmpleado['id_orden'] . ' AND id_ordenes_productos = ' . $miEmpleado['id_ordenes_productos'];
        } else {
            // TODO Verificar si ya hay una asignacion para hacer un `UPDATE` de lo contrario hacer un `INSERT`
            $sql = 'SELECT _id FROM lotes_detalles WHERE id_orden = ' . $miEmpleado['id_orden'] . ' AND id_ordenes_productos = ' . $miEmpleado['id_ordenes_productos'] . " AND departamento = '" . $miEmpleado['departamento'] . "'";

            $verificacion = $localConnection->goQuery($sql);
            $object['verificacion'] = $verificacion;

            if (empty($verificacion)) {
                // BUSCAR CANTIDAD EN `ordenes_productos`
                $sql = 'SELECT cantidad FROM ordenes_productos WHERE _id = ' . $miEmpleado['id_ordenes_productos'];
                $cantidad_orden = $localConnection->goQuery($sql)[0]['cantidad'];

                $myDate = new CustomTime();
                $now = $myDate->today();

                // ASIGNAR EMPLEADO
                $values = "'" . $now . "',";
                $values .= "'" . $miEmpleado['id_woo'] . "',";
                $values .= "'" . $cantidad_orden . "',";
                $values .= "'" . $miEmpleado['id_orden'] . "',";
                $values .= "'" . $miEmpleado['id_ordenes_productos'] . "',";
                $values .= "'" . $miEmpleado['id_empleado'] . "',";
                $values .= "'" . $miEmpleado['departamento'] . "'";

                $sql = 'INSERT INTO lotes_detalles (moment, id_woo, unidades_solicitadas, id_orden, id_ordenes_productos, id_empleado, departamento) VALUES (' . $values . ')';
            } else {
                // Hacer un UPDATE
                $sql = 'UPDATE lotes_detalles SET unidades_solicitadas = ' . $miEmpleado['cantidad'] . ', id_empleado = ' . $miEmpleado['id_empleado'] . ' WHERE id_orden = ' . $miEmpleado['id_orden'] . ' AND id_ordenes_productos = ' . $miEmpleado['id_ordenes_productos'] . " AND departamento = '" . $miEmpleado['departamento'] . "'";
            }
        }
        $object['sql_asignacion'] = $sql;

        $object['asigancion'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);

        // ACTUALIZAR PAGOS (UNICAMENTE SI AÚN NO SE HA PAGADO -> fechapago = NULL)

        /*  $values = "id_empleado ='" . $miEmpleado['id_empleado'] . "'";
            $sql = "UPDATE pagos SET " . $values . " WHERE departamento = '" . $miEmpleado['departamento'] . "' AND fecha_pago IS NULL AND id_orden = " . $miEmpleado['id_orden'];
            $object['lotes_pagos'] = $sql;
            $object['response_pagos'] = json_encode($localConnection->goQuery($sql)); */
    });

    $app->post('/lotes/get-detalles', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = 'SELECT id_orden, id_woo, category_name, name, cantidad, talla, corte, tela, moment FROM ordenes_productos WHERE id_woo = ' . $data['id_woo'] . ' AND talla =  ' . $data['talla'];

        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/lotes/update/cantidad', function (Request $request, Response $response, $args) {
        $data = $request->getParsedBody();
        $cantidad_orden = intval($data['cantidad_orden']);
        $cantidad_a_cortar = intval($data['cantidad_a_cortar']);
        $localConnection = new LocalDB();
        $object['request'] = $data;

        // -> -> VERIFICAR SI EL REGISTRO EXISTE EN `lotes_fisicos`
        $sql = "SELECT _id,  piezas_actuales FROM lotes_fisicos WHERE tela = '" . $data['tela'] . "' AND talla = '" . $data['talla'] . "' AND corte = '" . $data['corte'] . "' AND categoria = '" . $data['id_category'] . "'";

        $object['sql_count_lotes_fisicos'] = $sql;
        $cantidad_lotes_fisicos = $localConnection->goQuery($sql);
        $object['response_lotes_fisicos'] = $cantidad_lotes_fisicos;

        $last_id_lotes_fisicos = 0;

        if ($cantidad_a_cortar > 0) {
            // GUARDAR EN HISTORICO SOLICITADAS
            $sql = 'INSERT INTO lotes_historico_solicitadas (id_orden, id_lotes_fisicos, unidades_produccion) VALUES (' . $data['id_orden'] . ', ' . $last_id_lotes_fisicos . ', ' . $cantidad_a_cortar . ')';
            $object['response_insert_historico_solicitadas'] = $localConnection->goQuery($sql);
        }

        if (empty($cantidad_lotes_fisicos)) {
            $cantidad_unidades = $cantidad_a_cortar - $cantidad_orden;
            $object['dataResp'] = $cantidad_unidades;

            $sql = 'INSERT INTO lotes_fisicos (id_orden, id_woo, tela, talla, corte, categoria, piezas_actuales) VALUES (' . $data['id_orden'] . ', ' . $data['id_woo'] . ", '" . $data['tela'] . "', '" . $data['talla'] . "', '" . $data['corte'] . "', '" . $data['id_category'] . "', '" . $cantidad_unidades . "');";
            // $object['response_insert_lotes_fidicos'] = $localConnection->goQuery($sql);

            // OBTENER EL ULTIMO ID DE lotes_fisicos
            $last_prod = $localConnection->goQuery('SELECT MAX(_id) id FROM lotes_fisicos');
            $last_id_lotes_fisicos = intval($last_prod[0]['id']);
            // TODO ASIGNAT PAGO A CORTE CON LAS UNIDADES SOLICITADAS
        } else {
            // ACTUALIZAR EL REGISTRO EN `lotes_fisicos`
            $cantidad_unidades = (intval($data['cantidad_existencia']) - $cantidad_orden) + $cantidad_a_cortar;
            // $cantidad_unidades = intval($data["cantidad_existencia"]) + $cantidad_a_cortar;

            $sql = "UPDATE lotes_fisicos SET piezas_actuales = '" . $cantidad_unidades . "', id_woo = " . $data['id_woo'] . ' , id_orden = ' . $data['id_orden'] . ' WHERE _id = ' . $cantidad_lotes_fisicos[0]['_id'];
            // $object['response_get_lotes_fisicos'] = $localConnection->goQuery($sql);
            $object['dataResp'] = $object['response_lotes_fisicos'][0]['piezas_actuales'];
        }

        // VERIFICAR SI EXISTEN REGISTROS EN LA TABLA LOTES MOVIMIENTOS
        // GUARDAR EN lotes_movimientos SIEMPRE!!!
        $sql = 'SELECT _id FROM lotes_movimientos WHERE id_orden = ' . $data['id_orden'] . ' AND id_lotes_detalles = ' . $data['id'];
        $verificar_lm = $localConnection->goQuery($sql);

        if (empty($verificar_lm)) {
            // INSERT
            $sql = 'INSERT INTO lotes_movimientos (id_lotes_detalles, id_orden, unidades_existentes, unidades_solicitadas_corte) VALUES (' . $data['id'] . ', ' . $data['id_orden'] . ', ' . $cantidad_unidades . ', ' . $cantidad_a_cortar . ')';
        } else {
            $sql = 'UPDATE lotes_movimientos SET unidades_existentes = ' . $cantidad_unidades . ', unidades_solicitadas_corte = ' . $cantidad_a_cortar . ' WHERE id_orden = ' . $data['id_orden'] . ' AND id_lotes_detalles = ' . $data['id'];
            // UPDATE
        }
        $object['sql_revisar'] = $sql;
        $object['response_insert_lotes_movimientos'] = $localConnection->goQuery($sql);

        // CONSULTA DE RETORNO DE DATOS.
        if ($last_id_lotes_fisicos > 0) {
            // $last_id_lotes_fisicos = intval($cantidad_lotes_fisicos[0]["_id"]);
        }
        $sql = 'SELECT piezas_actuales FROM lotes_fisicos WHERE _id = ' . $last_id_lotes_fisicos;
        $cantidad_piezas = $localConnection->goQuery($sql);
        $object['cantidad_piezas'] = $cantidad_piezas;

        $sql = 'SELECT _id id_lotes_fisicos, piezas_actuales, tela, talla, corte, categoria, moment FROM lotes_fisicos';
        $object['lotes_fisicos'] = $localConnection->goQuery($sql);

        $object['sql_with_error'] = $sql;
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/lotes/update/prioridad', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = "UPDATE lotes SET prioridad = '" . $data['prioridad'] . "' WHERE _id = '" . $data['id'] . "'";

        $object['sql'] = $sql;
        $object['response_orden'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Editar la cantidad en lotes
    $app->get('/lotes-fisicos/tabla-editar', function (Request $request, Response $response) {
        $localConnection = new LocalDB();
        $sql = 'SELECT
    categoria categoria_tienda,
    tela,
    corte,
    talla,
    id_orden,
    id_woo,
    _id acciones,
    _id eliminar
    FROM
    lotes_fisicos
    ORDER BY tela ASC, corte ASC, talla ASC, piezas_actuales ASC';

        $object['items'] = $localConnection->goQuery($sql);

        $sql = 'SELECT * FROM catalogo_telas ORDER BY tela';
        $object['telas'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $woo = new WooMe();
        $object['categories'] = json_decode($woo->getAllCategories());

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/lotes-fisicos/tabla-editar-filter', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        if ($data['tela'] === 'all') {
            $sql = 'SELECT
        categoria categoria_tienda,
        tela,
        corte,
        talla,
        id_orden,
        id_woo,
        _id acciones,
        _id eliminar
        FROM
        lotes_fisicos
        ORDER BY tela ASC, corte ASC, talla ASC, piezas_actuales ASC';
        } else {
            $sql = "SELECT
        categoria categoria_tienda,
        tela,
        corte,
        talla,
        id_orden,
        id_woo,
        _id acciones,
        _id eliminar
        FROM
        lotes_fisicos
        WHERE tela = '" . $data['tela'] . "'
        ORDER BY tela ASC, corte ASC, talla ASC, piezas_actuales ASC";
        }

        $object['items'] = $localConnection->goQuery($sql);

        $sql = 'SELECT * FROM catalogo_telas ORDER BY tela';
        $object['telas'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $woo = new WooMe();
        $object['categories'] = json_decode($woo->getAllCategories());

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Eliminar lote
    $app->post('/lotes-fisicos/eliminar', function (Request $request, Response $response) {
        $miEmpleado = $request->getParsedBody();
        $localConnection = new LocalDB();

        $object['miEmpleado'] = $miEmpleado;
        $sql = 'DELETE FROM lotes_fisicos WHERE _id =  ' . $miEmpleado['id'];
        $object['sql'] = $sql;

        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/lotes-fisicos/update', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = "UPDATE lotes_fisicos SET piezas_actuales = '" . $data['cantidad'] . "' WHERE _id = '" . $data['id_lote'] . "'";

        $object['sql'] = $sql;
        $object['response_orden'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** LOCAL LOTES ACTIVOS */
    $app->get('/lotes/activos', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $sql = "SELECT a.lote, a.fecha, a.id_orden, a.paso, b.cliente_nombre FROM lotes a JOIN ordenes b ON a.id_orden = b._id WHERE b.status != 'pre-order' ORDER BY a.lote DESC";
        $object['lotes'] = $localConnection->goQuery($sql);

        $sql = 'SELECT a.id_orden, b.departamento, c.username empleado, b.producto, b.unidades_restantes, b.unidades_solicitadas, b.detalles, a.lote FROM lotes a JOIN lotes_detalles b ON a.id_orden = b.id_orden JOIN empleados c ON b.id_empleado = c._id';
        $object['lotes_detalles'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/lotes/fisicos', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT a.unidades FROM lotes_fisicos a JOIN inventario b ON a.id_inventario = b._id';
        $object['lotes'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/lotes/existencia/{talla}/{tela}/{corte}/{categoria}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = "SELECT piezas_actuales FROM lotes_fisicos WHERE talla = '" . $args['talla'] . "' AND tela = '" . $args['tela'] . "' AND corte = '" . $args['corte'] . "' AND categoria = '" . $args['categoria'] . "'";
        $response_lotes = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        if (empty($response_lotes)) {
            $cantidad = 0;
        } else {
            $cantidad = $response_lotes[0]['piezas_actuales'];
        }

        $response->getBody()->write(json_encode($cantidad));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** FIN LOTES */

    /** Asignacion */
    // Obtener datos para la asignaciond e empelados
    $app->get('/asignacion/ordenes', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $object['fields'][0]['key'] = 'orden';
        $object['fields'][0]['label'] = 'Orden';

        $object['fields'][1]['key'] = 'cliente';
        $object['fields'][1]['label'] = 'Cliente';

        $object['fields'][2]['key'] = 'inicio';
        $object['fields'][2]['label'] = 'Inicio';

        $object['fields'][3]['key'] = 'entrega';
        $object['fields'][3]['label'] = 'Entrega';

        $object['fields'][4]['key'] = 'status';
        $object['fields'][4]['label'] = 'Estatus';

        $object['fields'][4]['key'] = 'asignar';
        $object['fields'][4]['label'] = 'Asignar';

        $sql = "SELECT a._id orden, a._id asignar, a.cliente_nombre cliente, a.fecha_inicio inicio, a.fecha_entrega entrega, a.status estatus, b.terminado FROM `ordenes` a JOIN disenos b ON a._id = b.id_orden WHERE (a.status = 'activa' OR a.status = 'terminada' OR a.status = 'En espera' OR status = 'pausada') AND b.terminado = 1 OR b.tipo = 'no' ORDER BY a._id DESC";

        $object['items'] = $localConnection->goQuery($sql);
        $object['data'] = $object['items'];

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener empleados de la asignados de la orden
    $app->get('/asignacion/empleados/{orden}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT dep_responsable responsable,dep_diseno diseno, dep_jefe_diseno, dep_corte corte,dep_impresion impresion,dep_estampado estampado,dep_confeccion confeccion,dep_revision revision FROM ordenes WHERE _id = ' . $args['orden'];
        $object['sql'] = $sql;
        $object['data'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // GUARDAR ASIGNACION
    $app->post('/asignacion/{orden}/{departamento}/{empleado}', function (Request $request, Response $response, $args) {
        // ACTUALIZAR DATOS DE LA ORDEN
        $localConnection = new LocalDB();
        $departamento = 'dep_' . $args['departamento'];

        if ($args['empleado'] === 'none') {
            $sql_ordenes = 'UPDATE ordenes SET ' . $departamento . ' = NULL WHERE _id = ' . $args['orden'];
            $sql_pagos = 'DELETE FROM pagos WHERE id_orden = ' . $args['orden'] . " AND departamento = '" . $args['departamento'] . "';";
        } else {
            // BUSCAR COMISION DEL EMPLEADO PARA LA ORDEN
            $sql_ordenes = 'UPDATE ordenes SET ' . $departamento . ' = ' . $args['empleado'] . ' WHERE _id = ' . $args['orden'];
            $sql_comision = 'SELECT  comision FROM empleados WHERE _id = ' . $args['empleado'];
            $dataEmpleado = $localConnection->goQuery($sql_comision);

            $comision = $dataEmpleado[0]['comision'];
            // PREPARAR FECHAS
            $myDate = new CustomTime();
            $now = $myDate->today();

            // GUARDAR DATOS DEL PAGO
            $values = "'" . $now . "',";
            $values .= $args['empleado'] . ',';
            $values .= $args['orden'] . ',';
            $values .= "'" . $args['departamento'] . "',";
            $values .= "'0000-00-00',";
            $values .= '0,';
            $values .= $comision . ',';
            $values .= '0';

            $object['sql_pagos'] = $sql_pagos = 'DELETE FROM pagos WHERE id_orden = ' . $args['orden'] . " AND departamento = '" . $args['departamento'] . "';";
            $object['sql_pagos'] .= $sql_pagos = 'INSERT INTO pagos (moment, id_empleado, id_orden, departamento, fecha_terminado, dolar,  comision, pago) VALUES (' . $values . ')';
        }

        $dataEmpleado = $localConnection->goQuery($sql_ordenes);
        $dataEmpleado = $localConnection->goQuery($sql_pagos);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // ELIMINAR ASIGNACION
    $app->post('/asignacion/elimianr/{orden}/{departamento}', function (Request $request, Response $response, $args) {
        // ACTUALIZAR DATOS DE LA ORDEN
        $localConnection = new LocalDB();
        // ELIMINAR DATOS DEL PAGO
        $sql = 'DELETE FROM PAGOS WHERE id_orden = ' . $request['id_orden'] . ' AND departamento = ' . $args['departamento'];
        $object['dataEmpleado'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** Fin asignacion */

    /** PRODUCCION */

    // SSE PRODUCCION
    $app->get('/sse/produccion/ordenes-activas', function (Request $request, Response $response, array $args) {  // /lotes/en-proceso
        $sql = "SELECT 
                                                                                                                                                                                                                                                                                                                                                                a.id_orden orden, 
                                                                                                                                                                                                                                                                                                                                                                b.nombre AS empleado, 
                                                                                                                                                                                                                                                                                                                                                                c.name producto, 
                                                                                                                                                                                                                                                                                                                                                                c.cantidad, 
                                                                                                                                                                                                                                                                                                                                                                c.talla, 
                                                                                                                                                                                                                                                                                                                                                                c.corte, 
                                                                                                                                                                                                                                                                                                                                                                c.tela, 
                                                                                                                                                                                                                                                                                                                                                                DATE_FORMAT(a.fecha_inicio, '%h:%i:%s %p') AS hora, 
                                                                                                                                                                                                                                                                                                                                                                DATE_FORMAT(a.fecha_inicio, '%d-%m-%Y') AS fecha 
                                                                                                                                                                                                                                                                                                                                                                FROM lotes_detalles a 
                                                                                                                                                                                                                                                                                                                                                                JOIN empleados b ON a.id_empleado = b._id 
                                                                                                                                                                                                                                                                                                                                                                JOIN ordenes_productos c ON c._id = a.id_ordenes_productos
                                                                                                                                                                                                                                                                                                                                                                WHERE a.progreso = 'en curso' 
                                                                                                                                                                                                                                                                                                                                                                ORDER BY a.fecha_inicio DESC, b.nombre ASC
                                                                                                                                                                                                                                                                                                                                                                ";
        $obj[0]['sql'] = $sql;
        $obj[0]['name'] = 'items';

        $sse = new SSE($obj);
        $events = $sse->SsePrint();

        $response->getBody()->write(json_encode($events));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // SSE DATA
    // SSE PRODUCCION
    $app->get('/sse/produccion', function (Request $request, Response $response, array $args) {  // /lotes/en-proceso
        // $sql = "SELECT a._id orden, a._id vinculada, a.cliente_nombre cliente, b.prioridad, b.paso, a.fecha_inicio inicio, a.fecha_entrega entrega, a.observaciones detalles, a._id acciones, a.status estatus FROM ordenes a JOIN lotes b ON a._id = b.id_orden  WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' ORDER BY a._id DESC";
        $sql = "SELECT DISTINCT
      a._id AS orden,
      a._id AS vinculada,    
      a.cliente_nombre AS cliente,
      b.prioridad,
      b.paso,
      d.estatus AS estatus_revision,
      a.fecha_inicio AS inicio,
      a.fecha_entrega AS entrega,
      a.observaciones AS detalles,
      n.borrador AS detalle_empleado,
      a._id AS acciones,
      a.status AS estatus,
      c._id AS id_diseno,
      COALESCE(e.nombre, 'Sin asignar') AS disenador
      FROM
      ordenes a
      LEFT JOIN ordenes_borrador_empleado n ON a._id = n.id_orden
      JOIN lotes b ON a._id = b.id_orden
      LEFT JOIN disenos c ON a._id = c.id_orden
      LEFT JOIN revisiones d ON d.id_diseno = c._id
      LEFT JOIN empleados e ON e._id = CASE WHEN c.id_empleado = 0 THEN 0 ELSE c.id_empleado END
      WHERE
      (a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera')
      AND (SELECT COUNT(_id) FROM lotes_detalles WHERE id_orden = a._id) > 0
      ORDER BY a._id DESC;
    ";

        $obj[0]['sql'] = $sql;
        $obj[0]['name'] = 'items';

        // IDENTIFICAR QUE DEPARTAMENTOS ESTAN ASIGNADOS
        $sql = "SELECT a._id id_orden, b.departamento FROM lotes_detalles b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' GROUP BY a._id, b.departamento";
        $obj[1]['sql'] = $sql;
        $obj[1]['name'] = 'pactivos';

        // ORDENES VINCULADAS
        $sql = "SELECT b.id_father, b.id_child FROM ordenes_vinculadas b JOIN ordenes a ON a._id = b.id_father WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera'";
        $obj[2]['sql'] = $sql;
        $obj[2]['name'] = 'vinculadas';

        // EMPLEADOS
        $sql = 'SELECT _id, username, nombre, comision, departamento FROM empleados ORDER BY nombre ASC';
        $obj[3]['sql'] = $sql;
        $obj[3]['name'] = 'asignacion';

        $sql = "SELECT b.id_orden, b.paso from lotes b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera'";
        $obj[4]['sql'] = $sql;
        $obj[4]['name'] = 'pasos';

        $sql = "SELECT
        b._id,
        b.id_orden,
        (SELECT _id FROM lotes_fisicos WHERE id_orden = b._id) id_lotes, 
        b.id_woo,
        b.id_category,
        b.category_name,
        b.name,
        b.cantidad, 
        c.piezas_actuales,
        b.talla,
        b.corte,
        b.tela,
        b.precio_unitario,
        b.precio_woo,   
        b.moment
    FROM
        ordenes_productos b
    LEFT JOIN lotes_fisicos c ON c.id_orden = b._id
    JOIN ordenes a ON
        b.id_orden = a._id
    -- LEFT JOIN products p ON p._id = c.id_woo
    WHERE
        a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' AND b.category_name != 'Diseños' -- AND p.fisico = 1
    ORDER BY b._id DESC, c.piezas_actuales DESC";
        $obj[5]['sql'] = $sql;
        $obj[5]['name'] = 'orden_productos';

        $sql = "SELECT b._id, b.id_orden, b.id_woo, b.progreso, b.unidades_solicitadas cantidad, b.id_ordenes_productos, b.id_empleado, b.departamento, b.unidades_solicitadas, b.comision, b.detalles, b.fecha_inicio, b.fecha_terminado, b.moment FROM lotes_detalles b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera'";
        $obj[6]['sql'] = $sql;
        $obj[6]['name'] = 'lote_detalles';

        $sql = 'SELECT _id id_lotes_fisicos, piezas_actuales, tela, talla, corte, categoria, moment FROM lotes_fisicos';
        $obj[7]['sql'] = $sql;
        $obj[7]['name'] = 'lotes_fisicos';

        // $sql = "SELECT _id, id_orden, _id item, id_woo cod, name producto, cantidad, talla, tela, corte, precio_unitario precio, precio_woo precioWoo FROM ordenes_productos WHERE id_orden = " . $args["orden"] . " AND category_name != 'Diseños'";
        // $sql = "SELECT b._id, b.id_orden, b._id item, b.id_woo cod, b.name producto, b.cantidad, b.talla, b.tela, b.corte, b.precio_unitario precio, b.precio_woo precioWoo FROM ordenes_productos b JOIN ordenes a ON a._id = b.id_orden WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' AND fisico NOT LIKE 'Diseños'";

        $sql = "SELECT
        b._id,
        b.id_orden,
        b._id item,
        b.id_woo cod,
        b.name producto,
        b.cantidad,
        b.talla,
        b.tela,
        b.corte,
        b.precio_unitario precio,
        b.precio_woo precioWoo
    FROM
        ordenes_productos b
    JOIN ordenes a ON
        a._id = b.id_orden
    JOIN products p ON p._id = b.id_woo
    WHERE
        (a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera') AND p.fisico = 1";

        $obj[8]['sql'] = $sql;
        $obj[8]['name'] = 'reposicion_ordenes_productos';

        $sql = 'SELECT _id, _id acciones, username, password, nombre, email, departamento, comision, acceso FROM empleados ORDER BY nombre ASC';
        $obj[9]['sql'] = $sql;
        $obj[9]['name'] = 'empleados';

        // BUSCAR REPOSICIONES SOLICITADAS POR EMPLEADOS
        $sql = "SELECT
        a._id id_reposicion,
        a.id_orden,
        c._id id_ordenes_productos,
        b.nombre empleado,  
        a.detalle_emisor,
        DATE_FORMAT(a.moment, '%d/%m/%Y') AS fecha,
        DATE_FORMAT(a.moment, '%I:%i %p') AS hora,
        c.name producto,
        a.unidades,
        c.talla,
        c.corte,
        c.tela
    FROM
        reposiciones a
    LEFT JOIN empleados b ON b._id = a.id_empleado_emisor 
    JOIN ordenes_productos c ON c._id = a.id_ordenes_productos 
    WHERE
        a.aprobada = 0 AND a.id_empleado IS NULL";
        $obj[10]['sql'] = $sql;
        $obj[10]['name'] = 'reposiciones_solicitadas';

        // Deetalles de los productos
        $sql = "SELECT
        a._id id_orden,
        b._id id_lotes_detalles,
        b.name,
        b.cantidad,
        b.talla,
        b.corte,
        b.tela
    FROM
        ordenes a
    JOIN ordenes_productos b ON
        a._id = b.id_orden
    WHERE
        a.status LIKE 'En espera' OR a.status LIKE 'activa'
    ORDER BY
        b.id_orden ASC;";
        $obj[11]['sql'] = $sql;
        $obj[11]['name'] = 'productos';

        $sse = new SSE($obj);
        $events = $sse->SsePrint();

        // CREAR CAMPOS DE LA TABLA

        $object['fields'][0]['key'] = 'orden';
        $object['fields'][0]['label'] = 'Orden';

        $object['fields'][1]['key'] = 'cliente';
        $object['fields'][1]['label'] = 'Cliente';

        $object['fields'][2]['key'] = 'prioridad';
        $object['fields'][2]['label'] = 'Prioridad';

        $object['fields'][2]['key'] = 'paso';
        $object['fields'][2]['label'] = 'Progreso';

        $object['fields'][3]['key'] = 'inicio';
        $object['fields'][3]['label'] = 'Inicio';

        $object['fields'][4]['key'] = 'entrega';
        $object['fields'][4]['label'] = 'Entrega';

        $object['fields'][5]['key'] = 'vinculada';
        $object['fields'][5]['label'] = 'Vinculada';

        $object['fields'][6]['key'] = 'estatus';
        $object['fields'][6]['label'] = 'Estatus';

        $object['fields'][7]['key'] = 'detalles';
        $object['fields'][7]['label'] = 'Detalles';

        $object['fields'][8]['key'] = 'acciones';
        $object['fields'][8]['label'] = 'Acciones';

        // $sse = new SSE($obj);
        // $events = $sse->SsePrint();
        $response->getBody()->write(json_encode($events));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // SSE CORTE
    $app->get('/sse/produccion/corte/{id_empleado}', function (Request $request, Response $response, array $args) {  // /lotes/en-proceso
        $sql = 'SELECT 
                                                                                                                                                                                                                                                                                                                                                                        a._id AS id_lotes_detalles, 
                                                                                                                                                                                                                                                                                                                                                                        a.id_orden orden,
                                                                                                                                                                                                                                                                                                                                                                        a.id_orden acciones,
                                                                                                                                                                                                                                                                                                                                                                        b.name producto,
                                                                                                                                                                                                                                                                                                                                                                        b.cantidad, 
                                                                                                                                                                                                                                                                                                                                                                        b.cantidad cantidadIndividual, 
                                                                                                                                                                                                                                                                                                                                                                        a.progreso,
                                                                                                                                                                                                                                                                                                                                                                        b.talla, 
                                                                                                                                                                                                                                                                                                                                                                        b.corte, 
                                                                                                                                                                                                                                                                                                                                                                        b.tela,
                                                                                                                                                                                                                                                                                                                                                                        b.category_name categoria,
                                                                                                                                                                                                                                                                                                                                                                        COALESCE(c.piezas_actuales, 0) AS piezas_en_lote 
                                                                                                                                                                                                                                                                                                                                                                        FROM lotes_detalles a 
                                                                                                                                                                                                                                                                                                                                                                        JOIN ordenes_productos b 
                                                                                                                                                                                                                                                                                                                                                                        ON a.id_ordenes_productos = b._id 
                                                                                                                                                                                                                                                                                                                                                                        LEFT JOIN lotes_fisicos c ON c.tela = b.tela AND c.talla = b.talla AND c.corte = b.corte
                                                                                                                                                                                                                                                                                                                                                                        WHERE 
                                                                                                                                                                                                                                                                                                                                                                        a.id_empleado = ' . $args['id_empleado'] . " 
                                                                                                                                                                                                                                                                                                                                                                        AND a.departamento = 'Corte' 
                                                                                                                                                                                                                                                                                                                                                                        AND b.corte != 'No aplica' 
                                                                                                                                                                                                                                                                                                                                                                        AND (a.progreso = 'por iniciar' OR a.progreso = 'en curso')
                                                                                                                                                                                                                                                                                                                                                                        ORDER BY b.talla ASC, b.corte ASC, b.tela ASC;
                                                                                                                                                                                                                                                                                                                                                                        ";
        $obj[0]['sql'] = $sql;
        $obj[0]['name'] = 'items';

        $sql = "SELECT _id id_empleado, nombre FROM empleados WHERE departamento = 'Corte'";
        $obj[1]['sql'] = $sql;
        $obj[1]['name'] = 'empleados';

        $sse = new SSE($obj);
        $events = $sse->SsePrint();

        $response->getBody()->write(json_encode($events));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // SSE DISENO
    $app->get('/sse/diseno/{id_empleado}', function (Request $request, Response $response, array $args) {  // /lotes/en-proceso
        // $sql = "SELECT a._id orden, a._id vinculada, a.cliente_nombre cliente, b.prioridad, b.paso, a.fecha_inicio inicio, a.fecha_entrega entrega, a.observaciones detalles, a._id acciones, a.status estatus FROM ordenes a JOIN lotes b ON a._id = b.id_orden  WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' ORDER BY a._id DESC";
        $sql = 'SELECT
            a.linkdrive,
            a.codigo_diseno,
            a.id_orden,
            a._id id_diseno,
            a._id tallas_y_personalizacion,
            a.id_orden id,
            a.id_orden imagen,
            a.id_orden revision,
            a.linkdrive,
            b.cliente_nombre cliente,
            (SELECT cus.phone FROM customers cus WHERE cus._id = b.id_wp) phone,
            b.fecha_inicio inicio,
            a.tipo,
            c.estatus,
            b.status estatus_orden
        FROM
            disenos a
        LEFT JOIN revisiones c ON
            a._id = c.id_diseno 
        JOIN ordenes b ON
            b._id = a.id_orden 
        LEFT JOIN disenos d ON
            d._id = c.id_diseno
        WHERE
            a.id_empleado = ' . $args['id_empleado'] . " AND a.terminado = 0 AND(b.status = 'activa' OR b.status = 'pausada' OR b.status = 'En espera')
        ORDER BY
            a.id_orden
        DESC
            ";

        $obj[0]['sql'] = $sql;
        $obj[0]['name'] = 'items';

        // $sql = "SELECT a.id_diseno id, a.revision, a.detalles detalles_revision, a.id_orden FROM revisiones a JOIN disenos b ON b._id = a.id_diseno WHERE b.id_empleado = " . $args["id_empleado"];
        // $sql = "SELECT estatus, detalles FROM revisiones WHERE _id = " . $args["id"];
        $sql = 'SELECT a._id id_revision, a.id_diseno, a.id_orden, a.revision, a.estatus, a.detalles FROM revisiones a JOIN disenos b ON b.id_orden = a.id_orden WHERE b.id_empleado = ' . $args['id_empleado'] . ' ORDER BY a._id DESC';
        $obj[1]['sql'] = $sql;
        $obj[1]['name'] = 'revisiones';

        $sql = 'SELECT a.id_diseno, a.tipo, a.cantidad, b.id_orden FROM disenos_ajustes_y_personalizaciones a JOIN disenos b ON b._id = a.id_diseno WHERE b.id_empleado = ' . $args['id_empleado'];
        $obj[2]['sql'] = $sql;
        $obj[2]['name'] = 'ajustes';

        $sse = new SSE($obj);
        $events = $sse->SsePrint();

        // CREAR CAMPOS DE LA TABLA

        $object['fields'][0]['key'] = 'orden';
        $object['fields'][0]['label'] = 'Orden';

        $object['fields'][1]['key'] = 'cliente';
        $object['fields'][1]['label'] = 'Cliente';

        $object['fields'][2]['key'] = 'prioridad';
        $object['fields'][2]['label'] = 'Prioridad';

        $object['fields'][2]['key'] = 'paso';
        $object['fields'][2]['label'] = 'Progreso';

        $object['fields'][3]['key'] = 'inicio';
        $object['fields'][3]['label'] = 'Inicio';

        $object['fields'][4]['key'] = 'entrega';
        $object['fields'][4]['label'] = 'Entrega';

        $object['fields'][5]['key'] = 'vinculada';
        $object['fields'][5]['label'] = 'Vinculada';

        $object['fields'][6]['key'] = 'estatus';
        $object['fields'][6]['label'] = 'Estatus';

        $object['fields'][7]['key'] = 'detalles';
        $object['fields'][7]['label'] = 'Detalles';

        $object['fields'][8]['key'] = 'acciones';
        $object['fields'][8]['label'] = 'Acciones';

        $response->getBody()->write(json_encode($events));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER DATOS PARA LA ASIGNACION DE TALLAS Y PERSONALIZACION DE TODOS LAS ORDENES ACTIVAS.
    $app->get('/sse/disenos-todo', function (Request $request, Response $response, array $args) {  // /lotes/en-proceso
        $sql = "SELECT a._id id_orden, a._id tallas_personalizacion FROM ordenes a WHERE a.status = 'activa' OR a.status = 'pausada' OR a.status = 'En espera' ORDER BY a._id DESC";
        $obj[0]['sql'] = $sql;
        $obj[0]['name'] = 'items';

        // $sql = "SELECT a.id_diseno, a.tipo, a.cantidad, b.id_orden FROM disenos_ajustes_y_personalizaciones a JOIN disenos b ON b._id = a.id_diseno;";
        $sql = "SELECT
                                                                                                                                                                                                                                                                                                                                                                                a.id_diseno,
                                                                                                                                                                                                                                                                                                                                                                                a.tipo,
                                                                                                                                                                                                                                                                                                                                                                                a.cantidad,
                                                                                                                                                                                                                                                                                                                                                                                b.id_orden
                                                                                                                                                                                                                                                                                                                                                                                FROM
                                                                                                                                                                                                                                                                                                                                                                                ordenes o
                                                                                                                                                                                                                                                                                                                                                                                JOIN disenos_ajustes_y_personalizaciones a ON a.id_orden = o._id
                                                                                                                                                                                                                                                                                                                                                                                JOIN disenos b ON
                                                                                                                                                                                                                                                                                                                                                                                b._id = a.id_diseno
                                                                                                                                                                                                                                                                                                                                                                                WHERE o.status = 'activa' OR o.status = 'pausada' OR o.status = 'En espera' ORDER BY o._id DESC";
        $obj[2]['sql'] = $sql;
        $obj[2]['name'] = 'ajustes';

        $sse = new SSE($obj);
        $events = $sse->SsePrint();

        $response->getBody()->write(json_encode($events));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // FIN SSE DATA

    // OBTENER PASO DEL LOTE
    $app->get('/lotes/paso-actual/{id_orden}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        // BUSCAR PASO ACTUAL EN EL LOTE
        $sql = 'SELECT paso from lotes WHERE _id = ' . $args['id_orden'];
        $tmpPaso = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        if (!empty($tmpPaso)) {
            $object['paso'] = $tmpPaso[0]['paso'];
        } else {
            $object['paso'] = null;
        }

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    //  REPOSICIONES

    // obtener reposiciones de un item y orden especifico
    $app->get('/reposiciones/{id_ordenes_productos}/{id_orden}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT c.name producto, a.unidades,  c.talla, c.corte, c.tela, b.nombre empleado, detalle FROM reposiciones a JOIN empleados b ON a.id_empleado = b._id JOIN ordenes_productos c ON a.id_ordenes_productos = c._id WHERE a.id_ordenes_productos = ' . $args['id_ordenes_productos'] . ' AND a.id_orden = ' . $args['id_orden'];
        $object['sql'] = $sql;
        $object['data'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/produccion/reposicion', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();
        $sql = 'SELECT * FROM ordenes_productos WHERE _id = ' . $data['id_ordenes_productos'];
        $producto = $localConnection->goQuery($sql)[0];

        $myDate = new CustomTime();
        $now = $myDate->today();

        // Verificamos si se ha enviado la solicitud desde PRoduccion, lelgan los dos id de emploados
        if (isset($data['id_empleado_emisor'])) {
            // crear estructira de datos para los dos empleados
            $campos = '(moment, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle_emisor)';
            $values = '(';
            $values .= "'" . $now . "',";
            $values .= '' . $producto['id_orden'] . ',';
            $values .= '' . $data['id_empleado'] . ',';
            $values .= '' . $data['id_empleado_emisor'] . ',';
            $values .= '' . $producto['_id'] . ',';
            $values .= '' . $data['cantidad'] . ',';
            $values .= "'" . $data['detalle'] . "')";
        } else {
            $campos = '(moment, id_orden, id_empleado_emisor, id_ordenes_productos, unidades, detalle_emisor)';
            $values = '(';
            $values .= "'" . $now . "',";
            $values .= '' . $producto['id_orden'] . ',';
            $values .= '' . $data['id_empleado'] . ',';
            $values .= '' . $producto['_id'] . ',';
            $values .= '' . $data['cantidad'] . ',';
            $values .= "'" . $data['detalle'] . "')";
        }

        $sql = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
        $object['sql_insert_reposiciones'] = $sql;
        $object['response'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/produccion/reposicion/final', function (Request $request, Response $response) {
        /**
         * VAMOS A SACAR LA PARTE DE LA CREACIÓN DE LA REP[OSCICIÓN PUES ESTA LA ESTA HACCIENDO EL EMPLEADO
         * AQUI VAMOS A REASIGANAR EMPLEADOS Y DEMÁS COSAS QUE CONLLEVAN LA REPOSICIÓN
         */
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        // Validar si la reposicion ha sido aprobada
        if ($data['aprobada'] === '0') {
            $sql = "UPDATE reposiciones SET aprobada = 0, detalle = '" . $data['detalle'] . "' WHERE _id = " . $data['id_reposicion'];
            $aprobacion = $localConnection->goQuery($sql);
            $object['resp_reposiciones'] = $aprobacion;
        } else {
            $sql = "UPDATE reposiciones SET aprobada = 1, detalle = '" . $data['detalle'] . "', id_empleado = " . $data['id_empleado'] . ' WHERE _id = ' . $data['id_reposicion'];
            $object['sql_reposiciones'] = $sql;
            $aprobacion = $localConnection->goQuery($sql);

            // BUSCAR DATOS FALTANTES
            // Buscar ID del producto
            $sql = 'SELECT * FROM ordenes_productos WHERE _id = ' . $data['id_ordenes_productos'];
            $producto = $localConnection->goQuery($sql)[0];
            $id_woo = $producto['id_woo'];

            // BUSCAR DEPARTAMENTO DEL EMPLEADO PARA DETERMINAR LOS PASOS INVOLUCRADOS EN LA REPOSICIÓN Y ASIA SIGNARLES COMO TRABAJO LAS PIEZAS EN LOTES DETALLES.
            // $sql = 'SELECT departamento FROM empleados WHERE _id = ' . $data['id_empleado'];
            $sql = 'SELECT
                a.id_empleado_emisor,
                b.departamento
            FROM
                reposiciones a 
            JOIN empleados b ON b._id = a.id_empleado_emisor
            WHERE a._id = ' . $data['id_reposicion'];
            $object['sql_get_departamento_empleado'] = $sql;
            $departamento = $localConnection->goQuery($sql)[0]['departamento'];

            // DEVOLVER EL PASO A CORTE EN lotes
            // ASIGNAR NUEVAS TAREAS A EMPLEADOS ¿CREAR NUEVOS REGISTROS EN lotes_detalles?

            // -> BUSCAR DATOS EN ordenes_productos
            /* $sql = 'SELECT id_orden, id_woo FROM ordenes_productos WHERE _id = ' . $producto['_id'];
            $object['sql_get_idwoo_ordenes_productos'] = $sql;
            $object['result_ordenes_detalles'] = $localConnection->goQuery($sql)[0];
            $id_woo = $object['result_ordenes_detalles']['id_woo'];
            $object['id_woo'] = $object['result_ordenes_detalles']['id_woo']; */

            // TODO VERIFICAR EXISTENCIA EN LOTE Y NOTIFICAR A JEFE DE PRODUCCION

            // REASIGNAR TRABAJO A EMPLEADOS Y NO SE EXCLUIRÁ AL TRABAJADOR QUE ESTE INVOLUCRADO, ESO SE DECIDIRÁ AL MOMENTO DE SACAR EL REPORTE DE PAGOS
            $sql_lote_detalles = '';
            $sql_reposiciones = '';

            $object['departamento'] = $departamento;

            switch ($departamento) {
                case 'Impresión':
                    // IMPRESIÓN
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
                    $resp_emp_impresion = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_impresion)) {
                        $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

                        if (intval($id_emp_impresion != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_impresion . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_imp'] = $sqlr;
                            $id_rep_imp = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_imp = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_imp[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        }
                    }
                    break;

                case 'Estampado':
                    // IMPRESIÓN
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
                    $resp_emp_impresion = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_impresion)) {
                        $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

                        if (intval($id_emp_impresion != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_impresion . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_imp'] = $sqlr;
                            $id_rep_imp = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_imp = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_imp[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        }
                    }

                    // ESTAMPADO
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
                    $resp_emp_estampado = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_estampado)) {
                        $id_emp_estampado = $resp_emp_estampado[0]['id_empleado'];

                        if (intval($id_emp_estampado != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_estampado . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_est'] = $sqlr;
                            $id_rep_est = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_est = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_est[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                        }
                    }
                    break;
                case 'Corte':
                    // IMPRESIÓN
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
                    $resp_emp_impresion = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_impresion)) {
                        $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

                        if (intval($id_emp_impresion != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_impresion . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_imp'] = $sqlr;
                            $id_rep_imp = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_imp = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_imp[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        }
                    }

                    // ESTAMPADO
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
                    $resp_emp_estampado = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_estampado)) {
                        $id_emp_estampado = $resp_emp_estampado[0]['id_empleado'];

                        if (intval($id_emp_estampado != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_estampado . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_est'] = $sqlr;
                            $id_rep_est = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_est = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_est[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                        }
                    }

                    // CORTE
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
                    $resp_emp_corte = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_corte)) {
                        $id_emp_corte = $resp_emp_corte[0]['id_empleado'];

                        if (intval($id_emp_corte != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_corte . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $object['sqlr_cor'] = $sqlr;
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $id_rep_cor = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_cor = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_cor[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                        }
                    }

                    break;

                case 'Costura':
                    // IMPRESIÓN
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
                    $resp_emp_impresion = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_impresion)) {
                        $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

                        if (intval($id_emp_impresion != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_impresion . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_imp'] = $sqlr;
                            $id_rep_imp = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_imp = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_imp[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        }
                    }

                    // ESTAMPADO
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
                    $resp_emp_estampado = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_estampado)) {
                        $id_emp_estampado = $resp_emp_estampado[0]['id_empleado'];

                        if (intval($id_emp_estampado != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_estampado . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_est'] = $sqlr;
                            $id_rep_est = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_est = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_est[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                        }
                    }

                    // CORTE
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
                    $resp_emp_corte = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_corte)) {
                        $id_emp_corte = $resp_emp_corte[0]['id_empleado'];

                        if (intval($id_emp_corte != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_corte . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $object['sqlr_cor'] = $sqlr;
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $id_rep_cor = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_cor = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_cor[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                        }
                    }

                    // COSTURA
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Costura'";
                    $resp_emp_costura = $localConnection->goQuery($sqlw);
                    $id_emp_costura = $resp_emp_costura[0]['id_empleado'];

                    if (intval($id_emp_costura != intval($data['id_empleado']))) {
                        if (!empty($resp_emp_costura)) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_costura . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_cos'] = $sqlr;
                            $id_rep_cos = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_cos = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_cos[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
                        }
                    }

                case 'Limpieza':
                    // IMPRESIÓN
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
                    $resp_emp_impresion = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_impresion)) {
                        $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

                        if (intval($id_emp_impresion != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_impresion . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_imp'] = $sqlr;
                            $id_rep_imp = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_imp = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_imp[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        }
                    }

                    // ESTAMPADO
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
                    $resp_emp_estampado = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_estampado)) {
                        $id_emp_estampado = $resp_emp_estampado[0]['id_empleado'];

                        if (intval($id_emp_estampado != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_estampado . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_est'] = $sqlr;
                            $id_rep_est = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_est = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_est[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                        }
                    }

                    // CORTE
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
                    $resp_emp_corte = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_corte)) {
                        $id_emp_corte = $resp_emp_corte[0]['id_empleado'];

                        if (intval($id_emp_corte != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_corte . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $object['sqlr_cor'] = $sqlr;
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $id_rep_cor = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_cor = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_cor[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                        }
                    }

                    // COSTURA
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Costura'";
                    $resp_emp_costura = $localConnection->goQuery($sqlw);
                    $id_emp_costura = $resp_emp_costura[0]['id_empleado'];

                    if (intval($id_emp_costura != intval($data['id_empleado']))) {
                        if (!empty($resp_emp_costura)) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_costura . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_cos'] = $sqlr;
                            $id_rep_cos = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_cos = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_cos[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
                        }
                    }

                    // LIMPIEZA
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Limpieza'";
                    $resp_emp_limpieza = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_limpieza)) {
                        $id_emp_limpieza = $resp_emp_limpieza[0]['id_empleado'];

                        if (intval($id_emp_limpieza != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_limpieza . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $object['sqlr_lim'] = $sqlr;
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $id_rep_lim = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_lim = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_lim[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_limpieza . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_limpieza . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
                        }
                    }

                    break;

                case 'Revisión':
                    // IMPRESIÓN
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
                    $resp_emp_impresion = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_impresion)) {
                        $id_emp_impresion = $resp_emp_impresion[0]['id_empleado'];

                        if (intval($id_emp_impresion != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_impresion . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_imp'] = $sqlr;
                            $id_rep_imp = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_imp = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_imp[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                        }
                    }

                    // ESTAMPADO
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
                    $resp_emp_estampado = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_estampado)) {
                        $id_emp_estampado = $resp_emp_estampado[0]['id_empleado'];

                        if (intval($id_emp_estampado != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_estampado . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_est'] = $sqlr;
                            $id_rep_est = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_est = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_est[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                        }
                    }

                    // CORTE
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
                    $resp_emp_corte = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_corte)) {
                        $id_emp_corte = $resp_emp_corte[0]['id_empleado'];

                        if (intval($id_emp_corte != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_corte . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $object['sqlr_cor'] = $sqlr;
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $id_rep_cor = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_cor = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_cor[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                        }
                    }

                    // COSTURA
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Costura'";
                    $resp_emp_costura = $localConnection->goQuery($sqlw);
                    $id_emp_costura = $resp_emp_costura[0]['id_empleado'];

                    if (intval($id_emp_costura != intval($data['id_empleado']))) {
                        if (!empty($resp_emp_costura)) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_costura . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_cos'] = $sqlr;
                            $id_rep_cos = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_cos = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_cos[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
                        }
                    }

                    // LIMPIEZA
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Limpieza'";
                    $resp_emp_limpieza = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_limpieza)) {
                        $id_emp_limpieza = $resp_emp_limpieza[0]['id_empleado'];

                        if (intval($id_emp_limpieza != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_limpieza . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $object['sqlr_lim'] = $sqlr;
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $id_rep_lim = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_lim = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_lim[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_limpieza . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_limpieza . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
                        }
                    }

                    // REVISION
                    $sqlw = 'SELECT id_empleado, id_orden, id_empleado, id_ordenes_productos FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Revisión'";
                    $resp_emp_revision = $localConnection->goQuery($sqlw);

                    if (!empty($resp_emp_revision)) {
                        $id_emp_revision = $resp_emp_revision[0]['id_empleado'];

                        if (intval($id_emp_revision != intval($data['id_empleado']))) {
                            $campos = '(moment, aprobada, id_orden, id_empleado, id_empleado_emisor, id_ordenes_productos, unidades, detalle, detalle_emisor)';
                            $values = '(';
                            $values .= "'" . $now . "',";
                            $values .= '1,';
                            $values .= '' . $data['id_orden'] . ',';
                            $values .= '' . $id_emp_revision . ',';
                            $values .= '' . $data['id_empleado_emisor'] . ',';
                            $values .= '' . $data['id_ordenes_productos'] . ',';
                            $values .= '' . $data['cantidad'] . ',';
                            $values .= "'" . $data['detalle'] . "',";
                            $values .= "'" . $data['detalle_emisor'] . "')";
                            $sqlr = 'INSERT INTO reposiciones ' . $campos . ' VALUES ' . $values;
                            $object['sqlr_rev'] = $sqlr;
                            $id_rep_rev = $localConnection->goQuery($sqlr);

                            $sqlr = 'SELECT MAX(_id) id FROM reposiciones';
                            $id_rep_rev = $localConnection->goQuery($sqlr);
                            $id_rep = $id_rep_rev[0]['id'];

                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $id_rep . ", '" . $id_emp_revision . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Revisión', '" . $data['detalle'] . "');";
                        } else {
                            $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_revision . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Revisión', '" . $data['detalle'] . "');";
                        }
                    }
                    break;

                default:
                    $sql_lote_detalles = '';
                    break;
            }

            $object['sql_insert_lotes_detalles'] = $sql_lote_detalles;

            if (!empty($sql_lote_detalles)) {
                $object['result_insert_lotes_detalles'] = $localConnection->goQuery($sql_lote_detalles);
            }
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/produccion/reposicion/final/BAK', function (Request $request, Response $response) {
        /**
         * VAMOS A SACAR LA PARTE DE LA CREACIÓN DE LA REP[OSCICIÓN PUES ESTA LA ESTA HACCIENDO EL EMPLEADO
         * AQUI VAMOS A REASIGANAR EMPLEADOS Y DEMÁS COSAS QUE CONLLEVAN LA REPOSICIÓN
         */
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        // Validar si la reposicion ha sido aprobada
        if ($data['aprobada'] === '0') {
            $sql = "UPDATE reposiciones SET aprobada = 0, detalle = '" . $data['detalle'] . "' WHERE _id = " . $data['id_reposicion'];
            $aprobacion = $localConnection->goQuery($sql);
            $object['resp_reposiciones'] = $aprobacion;
        } else {
            $sql = "UPDATE reposiciones SET aprobada = 1, detalle = '" . $data['detalle'] . "', id_empleado = " . $data['id_empleado'] . ' WHERE _id = ' . $data['id_reposicion'];
            $object['sql_reposiciones'] = $sql;
            $aprobacion = $localConnection->goQuery($sql);

            // BUSCAR DATOS FALTANTES
            // Buscar ID del producto
            $sql = 'SELECT * FROM ordenes_productos WHERE _id = ' . $data['id_ordenes_productos'];
            $producto = $localConnection->goQuery($sql)[0];
            $id_woo = $producto['id_woo'];

            // BUSCAR DEPARTAMENTO DEL EMPLEADO PARA DETERMINAR LOS PASOS INVOLUCRADOS EN LA REPOSICIÓN Y ASIA SIGNARLES COMO TRABAJO LAS PIEZAS EN LOTES DETALLES.
            $sql = 'SELECT departamento FROM empleados WHERE _id = ' . $data['id_empleado'];
            $object['sql_get_departamento_empleado'] = $sql;
            $departamento = $localConnection->goQuery($sql)[0]['departamento'];

            // DEVOLVER EL PASO A CORTE EN lotes
            // ASIGNAR NUEVAS TAREAS A EMPLEADOS ¿CREAR NUEVOS REGISTROS EN lotes_detalles?

            // -> BUSCAR DATOS EN ordenes_productos
            /* $sql = 'SELECT id_orden, id_woo FROM ordenes_productos WHERE _id = ' . $producto['_id'];
            $object['sql_get_idwoo_ordenes_productos'] = $sql;
            $object['result_ordenes_detalles'] = $localConnection->goQuery($sql)[0];
            $id_woo = $object['result_ordenes_detalles']['id_woo'];
            $object['id_woo'] = $object['result_ordenes_detalles']['id_woo']; */

            // TODO VERIFICAR EXISTENCIA EN LOTE Y NOTIFICAR A JEFE DE PRODUCCION

            // REASIGNAR TRABAJO A EMPLEADOS Y NO SE EXCLUIRÁ AL TRABAJADOR QUE ESTE INVOLUCRADO, ESO SE DECIDIRÁ AL MOMENTO DE SACAR EL REPORTE DE PAGOS
            $sql_lote_detalles = '';
            $sql_reposiciones = '';
            switch ($departamento) {
                case 'Impresión':
                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
                    $id_emp_corte = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $data['id_empleado'] . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                    break;

                case 'Estampado':
                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
                    $id_emp_corte = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
                    $id_emp_impresion = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $data['id_empleado'] . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                    break;

                case 'Corte':
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`, `id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $data['id_empleado'] . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                    break;

                case 'Costura':
                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
                    $id_emp_corte = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
                    $id_emp_impresion = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
                    $id_emp_estampado = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= "INSERT INTO lotes_detalles (`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES ('" . $data['id_empleado'] . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
                    break;

                case 'Limpieza':
                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
                    $id_emp_corte = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
                    $id_emp_impresion = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
                    $id_emp_estampado = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Costura'";
                    $id_emp_costura = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $data['id_empleado'] . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
                    break;

                case 'Revisión':
                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Impresión'";
                    $id_emp_impresion = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Estampado'";
                    $id_emp_estampado = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Corte'";
                    $id_emp_corte = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Costura'";
                    $id_emp_costura = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Limpieza'";
                    $id_emp_limpieza = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sqlw = 'SELECT id_empleado FROM lotes_detalles WHERE id_ordenes_productos = ' . $data['id_ordenes_productos'] . ' AND id_orden = ' . $data['id_orden'] . " AND departamento = 'Revisión'";
                    $id_emp_revision = $localConnection->goQuery($sqlw)[0]['id_empleado'];

                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_impresion . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Impresión', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_estampado . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Estampado', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_corte . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Corte', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_costura . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Costura', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_limpieza . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Limpieza', '" . $data['detalle'] . "');";
                    $sql_lote_detalles .= 'INSERT INTO lotes_detalles (`id_reposicion`,`id_empleado`, `unidades_solicitadas`, `moment`, `id_orden`, `id_ordenes_productos`, `id_woo`, `departamento`, detalles) VALUES (' . $data['id_reposicion'] . ", '" . $id_emp_revision . "', '" . $data['cantidad'] . "', '" . $now . "', '" . $producto['id_orden'] . "', '" . $producto['_id'] . "', '" . $id_woo . "', 'Revisión', '" . $data['detalle'] . "');";
                    break;

                default:
                    $sql_lote_detalles = '';
                    break;
            }

            $object['sql_insert_lotes_detalles'] = $sql_lote_detalles;

            if (!empty($sql_lote_detalles)) {
                // $object['result_insert_lotes_detalles'] = $localConnection->goQuery($sql_lote_detalles);
            }
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // TERMINAR CICLO DE PRODUCCION
    $app->post('/produccion/terminar/{id}', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        $localConnection = new LocalDB();

        $sql = "UPDATE `ordenes` SET `status`='terminado' WHERE `_id` = " . $id;
        $object['response'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // ASIGNAR VARIAS ORDENES A CORTE A LA VEZ
    $app->post('/produccion/asignar-varias-ordenes-a-corte', function (Request $request, Response $response, array $args) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $object['data'] = $data;
        $object['request_data'] = json_decode($data['data']);

        $sql = '';
        foreach ($object['request_data'] as $key => $item) {
            $sql .= 'UPDATE lotes_detalles SET id_empleado = ' . $data['id_empleado'] . ', unidades_solicitadas = ' . $object['request_data'][$key]->cantidad . '  WHERE _id = ' . $object['request_data'][$key]->id_lotes_detalles . ';';
        }

        $object['response_update'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        /*
         * $listaDeIdsDetalles = explode(',', $data['id_lotes_detalles']);
         *         $countIdLotesDetalles = count($listaDeIdsDetalles);
         *         $listaDeIdsOrdenes = explode(',', $data['ordenes']);
         * // BUSCAR EN ordenes_productos
         *         $sql = "";
         *         foreach ($listaDeIdsDetalles as $idLoteDetalles) {
         * // $sql2 = "SELECT cantidad FROM ordenes_productos WHERE id_orden = " . $data["id_orden"];
         *             $sql2 = "SELECT a.cantidad FROM ordenes_productos a JOIN lotes_detalles b ON a._id = b.id_ordenes_productos WHERE b.id_orden = " . $data[""];
         *             $cantidadPiezas = $localConnection->goQuery($sql2);
         *
         *             $sql .= "UPDATE lotes_detalles SET id_empleado = " . $data["id_empleado"] . " WHERE _id = " . $idLoteDetalles . ";";
         *         }
         *         $object['response'] = $localConnection->goQuery($sql);
         */

        // PRETARA UPDATE
        /* $UpdateParams = "(";
                                 foreach ($listaDeIdsDetalles as $idLoteDetalles) {
                                    $UpdateParams .= "";
                                }
                                $sql = "";
                                foreach ($listaDeIdsOrdenes as $idOrden) {
                                    $sql .= "UPDATE lotes_detalles SET id_empleado = " . $data["id_epleado"] . " WHERE id_orden = " . $idOrden . " AND ";
                                } */

        // $response->getBody()->write(json_encode($object));
        $response->getBody()->write(json_encode($sql));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // UPDATE PASO
    $app->post('/produccion/update/paso', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        // VERIFCAR SI EXISTE PERSONAL ASIGNADO APR ESTE PRODUCTO EN EL LOTE
        $sql = 'SELECT COUNT(*) cuenta FROM lotes_detalles WHERE id_orden = ' . $data['id_orden'] . " AND departamento = '" . $data['paso'] . "'";
        $object['sql_empty'] = $sql;
        $cuenta = $localConnection->goQuery($sql);

        $asignados = $cuenta[0]['cuenta'];
        $object['asignados'] = $cuenta[0]['cuenta'];
        $object['empty'] = empty($asignados);

        if (empty($asignados)) {
            $object['nodata'] = true;
        } else {
            // TODO buscar datos para el calculo de pagos
            $sql = "UPDATE lotes SET paso = '" . $data['paso'] . "' WHERE _id = '" . $data['id_orden'] . "'";
            $object['response_orden'] = json_encode($localConnection->goQuery($sql));
            $object['nodata'] = false;
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // PROGRESSBAR
    $app->get('/produccion/progressbar/{id_orden}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        // VERIFCAR STATUS DE LA ORDEN
        $sql = 'SELECT status from ordenes WHERE _id = ' . $args['id_orden'];
        $tmpStatus = $localConnection->goQuery($sql);
        $object['status'] = $tmpStatus[0]['status'];

        // BUSCAR PASO ACTUAL EN EL LOTE
        // $sql = "SELECT paso from lotes WHERE _id = " . $args["id_orden"];
        $sql = 'SELECT paso from lotes WHERE id_orden = ' . $args['id_orden'];
        $tmpPaso = $localConnection->goQuery($sql);
        $object['paso'] = $tmpPaso[0]['paso'];

        // BUSCAR TIPO DE DISEÑO
        $sql = 'SELECT a.tipo, a.id_empleado, b.nombre FROM disenos a JOIN empleados b ON b._id = a.id_empleado WHERE id_orden = ' . $args['id_orden'];
        $d = $localConnection->goQuery($sql);

        if (empty($d)) {
            $diseno = 'no';
        } else {
            $diseno = $d[0]['tipo'];
        }

        if ($diseno === 'no') {
            $cuentaDisenos = 0;
        } else {
            $cuentaDisenos = 2;
        }
        $object['data']['cuentaDisenos'] = $cuentaDisenos;

        // IDENTIFICAR QUE DEPARTAMENTOS ESTAN ASIGNADOS
        $sql = 'SELECT `departamento` FROM lotes_detalles WHERE id_orden = ' . $args['id_orden'] . ' GROUP BY departamento';
        $pActivos = $localConnection->goQuery($sql);
        $object['data']['pActivos'] = $pActivos;

        switch ($object['paso']) {
            case 'producción':
                $x[] = 0.6;
                break;

            case 'Corte':
                $x[] = 1;
                break;

            case 'Estampado':
                $x[] = 2;
                break;

            case 'Impresión':
                $x[] = 3;
                break;

            case 'Costura':
                $x[] = 4;
                break;

            case 'Limpieza':
                $x[] = 5;
                break;

            case 'Revisión':
                $x[] = 5.88;
                break;

                /*  case 'Diseno':
                    $x[] = 0;
                    break; */

            default:
                $x[] = 1;
                break;
        }

        $pasoActual = max($x);
        $object['data']['pasoActual'] = $pasoActual;
        $totalPasos = count($pActivos);
        $object['data']['totalPasos'] = count($pActivos);

        if (!$totalPasos) {
            $totalPasos = 1;
        }

        $object['porcentaje'] = round($pasoActual * 100 / $totalPasos);
        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Detalles para la asignacion de personal V2
    $app->get('/lotes/detalles/v2/{id}', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        $localConnection = new LocalDB();

        // OBTENER PRODUCTOS DEL LOTE
        // EXCLUIR DISEÑOS FILTRANDO POR NOMBRE
        $sql = "SELECT * FROM ordenes_productos WHERE category_name != 'Diseños' AND id_orden = " . $id;
        $object['query_orden_productos'] = $sql;
        $object['orden_productos'] = $localConnection->goQuery($sql);

        $sql = 'SELECT * FROM lotes_detalles WHERE id_orden = ' . $id;
        $object['query_lotes_detalle'] = $sql;
        $object['lote_detalles'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // VERIFICAR SI EXISTE EMPLEADO ASIGNADO PARA ASIGNACION DE EMPLEADOS EN PRDUCCIÓN
    $app->get('/produccion/verificar-asignacion-empleado/{departamento}/{id_orden}/{id_ordenes_productos}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        /* $sql = "SELECT id_empleado FROM lotes_detalles WHERE id_orden = " . $args["id_orden"] . " AND id_ordenes_productos = " . $args["id_ordenes_productos"] . " AND departamento = '" . $args["departamento"] . "'";
        $object["sql"] = $sql; */

        $sql = 'SELECT
        lot.id_empleado, 
        emp.departamento emp_departamento,
        lot.departamento lot_departamento
    FROM
        lotes_detalles lot
      LEFT JOIN empleados emp ON lot.id_empleado = emp._id
    WHERE
        lot.id_orden = ' . $args['id_orden'] . ' AND lot.id_ordenes_productos = ' . $args['id_ordenes_productos'] . " AND lot.departamento = '" . $args['departamento'] . "'";
        $object['sql'] = $sql;

        $resp = $localConnection->goQuery($sql);

        if (count($resp)) {
            $object = $resp[0];
        } else {
            $object['OKOK'] = $resp;
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Detalles para la asignacion de personal
    $app->get('/lotes/detalles/{id}', function (Request $request, Response $response, array $args) {
        $id = $args['id'];
        $localConnection = new LocalDB();

        // OBTENER LOTE
        $sql = 'SELECT _id, lote, fecha, id_orden, paso  FROM lotes WHERE _id = ' . $id;
        $object['lote'] = $localConnection->goQuery($sql);

        // OBTENER PRODUCTOS DEL LOTE
        $sql = 'SELECT _id, name producto FROM ordenes_productos WHERE id_orden = ' . $id;
        $object['orden_productos'] = $localConnection->goQuery($sql);

        // OBTENER PAGOS
        $sql = 'SELECT * FROM pagos WHERE id_orden = ' . $id;
        $object['orden_pagos'] = $localConnection->goQuery($sql);

        // OBTENER DETALLES DEL LOTE
        $sql = 'SELECT * FROM lotes_detalles WHERE id_orden = ' . $id;
        $object['lote_detalles'] = $localConnection->goQuery($sql);
        $object['lote_detalles_SQL'] = $sql;

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // obtener detalles de empleados de la orden

    $app->get('/ordenes/detalles/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT observaciones FROM ordenes WHERE _id = ' . $args['id'];
        $object['sql'] = $sql;
        $object['detalle'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // obtener ordenes vinculadas

    $app->get('/ordenes/vinculadas/{id_orden_father}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT id_child FROM ordenes_vinculadas WHERE id_father = ' . $args['id_orden_father'];
        $vinculadas = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($vinculadas));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** FIN PRODUCCION */

    /** TRUNCAR ORDER Y LOTES */
    $app->post('/truncate', function (Request $request, Response $response) {
        $localConnection = new LocalDB();
    
        // Deshabilitar las restricciones de claves foráneas y truncar las tablas
        $sql = 'SET FOREIGN_KEY_CHECKS = 0;
            TRUNCATE `abonos`;
            TRUNCATE `aprobacion_clientes`;
            TRUNCATE `asistencias`;
            TRUNCATE `caja`;
            TRUNCATE `caja_cierres`;
            TRUNCATE `caja_fondos`;
            TRUNCATE `disenos`;
            TRUNCATE `disenos_ajustes_y_personalizaciones`;
            TRUNCATE `inventario_movimientos`;
            TRUNCATE `lotes`;
            TRUNCATE `lotes_detalles`;
            TRUNCATE `lotes_fisicos`;
            TRUNCATE `lotes_historico_solicitadas`;
            TRUNCATE `lotes_movimientos`;
            TRUNCATE `metodos_de_pago`;
            TRUNCATE `ordenes`;
            TRUNCATE `ordenes_borrador_empleado`;
            TRUNCATE `ordenes_productos`;
            TRUNCATE `ordenes_tmp`;
            TRUNCATE `ordenes_vinculadas`;
            TRUNCATE `pagos`;
            TRUNCATE `piezas_cortadas`;
            TRUNCATE `presupuestos`;
            TRUNCATE `presupuestos_productos`;
            TRUNCATE `rendimiento`;
            TRUNCATE `reposiciones`;
            TRUNCATE `retiros`;
            TRUNCATE `revisiones`;
            TRUNCATE `tintas`;
            SET FOREIGN_KEY_CHECKS = 1;
        ';
    
        // Ejecutar el comando de truncado
        $localConnection->goQuery($sql);
    
        // Obtener la lista de tablas y su cantidad de registros
        $sql_tables = "
            SELECT 
                table_name AS 'Tabla', 
                table_rows AS 'Registros' 
            FROM 
                information_schema.tables 
            WHERE 
                table_schema = DATABASE() 
            ORDER BY 
                table_name;
        ";
    
        $table_data = $localConnection->goQuery($sql_tables);
        $localConnection->disconnect();
    
        // Preparar la respuesta con la lista de tablas y su cantidad de registros
        $response->getBody()->write(json_encode([
            'message' => 'Tablas truncadas y registros contados correctamente.',
            'tables' => $table_data,
        ]));
    
        return $response->withHeader('Content-Type', 'application/json')->withStatus(200);
    });
    

    /** Revisión */

    // CREAR UNA NUEVA REVISIONrevision
    $app->post('/revision/nuevo', function (Request $request, Response $response) {
        $miRevision = $request->getParsedBody();
        $localConnection = new LocalDB();

        // verificar que la revision exista
        $sql = 'SELECT _id FROM revisiones WHERE id_diseno = ' . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'];
        $object['sql_count'] = $sql;
        // obtener numero de la última revision
        $object['exist'] = $exist = $localConnection->goQuery($sql);

        if (count($exist) > 0) {
            // UPDATE
            $sql = "UPDATE revisiones SET estatus = 'Esperando Respuesta' WHERE id_diseno = " . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'];
            $object['response_update'] = json_encode($localConnection->goQuery($sql));
            $localConnection->disconnect();

            /* $response->getBody()->write(json_encode($object));
            return $response
              ->withHeader('Content-Type', 'application/json')
              ->withStatus(200); */
        } else {
            $object['sql_MAX_REVIEW'] = $sql;
            $sql = 'SELECT MAX(revision) revision FROM revisiones WHERE id_diseno = ' . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'];
            $tmpRevID = $localConnection->goQuery($sql);

            if ($tmpRevID[0]['revision'] === null) {
                $currID = 1;
            } else {
                $currID = intval($tmpRevID[0]['revision']) + 1;
            }

            // CREAR REVISION
            $values = '(';
            $values .= "'" . $miRevision['id_diseno'] . "',";
            $values .= "'" . $miRevision['id_orden'] . "',";
            $values .= "'" . $currID . "')";

            $sql = 'INSERT INTO revisiones (`id_diseno`, `id_orden`, `revision`) VALUES ' . $values;
            $object['response_insert'] = json_encode($localConnection->goQuery($sql));

            $object['sql_insert'] = $sql;

            $sql =
                'SELECT * FROM revisiones WHERE id_diseno = ' . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'];
            $tmpRevision = $localConnection->goQuery($sql);

            if (count($tmpRevision) > 0) {
                $object['revision'] = $tmpRevision[0];
            } else {
                $object['revision'] = $tmpRevision;
            }

            $object['sql_get_review'] = $sql;

            // obtener numero de la última revision
            $sql = 'SELECT MAX(revision) revision FROM revisiones WHERE id_diseno = ' . $miRevision['id_diseno'] . ' AND id_orden = ' . $miRevision['id_orden'];

            $object['sql_MAX_REVIEW'] = $sql;
            $object['lastId'] = $localConnection->goQuery($sql);

            $object['image_name'] = $miRevision['id_orden'] . '-' . $miRevision['id_diseno'] . '-' . $object['lastId'][0]['revision'];

            $sql = "SELECT a.id_orden imagen, a.id_orden vinculadas, a.tipo, a.id_orden id, a.id_empleado empleado, b.responsable FROM disenos a JOIN ordenes b ON b._id = a.id_orden WHERE a.id_empleado = '" . $miRevision['id_empleado'] . "' a.tipo != 'no' AND (a.terminado = 0 AND b.status != 'entregada' AND b.status != 'cancelada' AND b.status != 'terminado')";
            $object['new_data'] = $localConnection->goQuery($sql);
        }

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER DATOS DE LA REVISION DE UN DISEÑO POR SU ID
    $app->get('/revision/diseno/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT _id id_revision, id_diseno, id_orden, revision, estatus, detalles FROM revisiones a WHERE id_orden = ' . $args['id'] . ' ORDER BY _id DESC';
        $object['sql'] = $sql;
        $object = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER ESTATUS DE LA REVISION
    $app->get('/revisiones/estatus/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT estatus, detalles FROM revisiones WHERE _id = ' . $args['id'];
        // $object = $localConnection->goQuery($sql)[0];
        $object = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Datos para la revisiond e trabajos
    $app->get('/revision/trabajos', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = "SELECT a._id id_lotes_detalles, a.id_orden, b.name producto, b.cantidad, c.nombre empleado, d.estatus, d._id id_pagos, e.status estatus_orden FROM lotes_detalles a JOIN ordenes_productos b ON a.id_ordenes_productos = b._id JOIN empleados c ON a.id_empleado = c._id JOIN pagos d ON d.id_lotes_detalles = a._id JOIN ordenes e ON e._id = a.id_orden WHERE (e.status = 'Activa' OR e.status = 'Pausada' OR e.status = 'En espera') AND d.estatus = 'aprobado'";
        $object['sql'] = $sql;
        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Update estatus de pago
    $app->get('/revision/actualizar-estatus-de-pago/{estatus}/{id_pago}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = "UPDATE pagos SET estatus = '" . $args['estatus'] . "' WHERE _id = " . $args['id_pago'];
        $object['sql'] = $sql;
        $object['save'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** Empleados */

    // Guardar Treas
    $app->post('/empleados/tareas', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $time_terminado = $myDate->today();

        $sql = 'UPDATE lotes_detalles SET terminado = ' . $data['terminado'] . ", fecha_terminado = '" . $time_terminado . "' WHERE _id = " . $data['id_lotes_detalles'];
        // $object['sql'] = $sql;
        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Guardar tintas
    $app->post('/empleados/tintas', function (Request $request, Response $response) {
        $misTintas = $request->getParsedBody();
        $localConnection = new LocalDB();

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        // Crear estructura de valores para insertar nuevo cliente
        $values = '(';
        $values .= "'" . $now . "',";
        $values .= "'" . $misTintas['c'] . "',";
        $values .= "'" . $misTintas['m'] . "',";
        $values .= "'" . $misTintas['y'] . "',";
        $values .= "'" . $misTintas['k'] . "',";
        $values .= "'" . $misTintas['id_orden'] . "',";
        $values .= "'" . $misTintas['id_empleado'] . "')";

        $sql = 'INSERT INTO tintas (`moment`, `c`, `m`, `y`, `k`, `id_orden`, `id_empleado`) VALUES ' . $values;
        $object['sql'] = $sql;

        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Cargar datos adicionales para el calculo del rendimiento del material
    $app->post('/insumos/rendimiento', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        // 0- Preparar datos
        if ($data['departamento'] === 'Impresión') {
            $campo_valor = 'metros';
            $campo_empleado = 'id_empleado_impresion';
        }
        if ($data['departamento'] === 'Estampado') {
            $campo_valor = 'id_insumo';
            $campo_empleado = 'id_empleado_estampado';
        }
        if ($data['departamento'] === 'Corte') {
            $campo_valor = 'desperdicio';
            $campo_empleado = 'id_empleado_corte';
        }

        // 1- Determinar si el registro existe (INSERT o UPDATE)
        $sql = 'SELECT COUNT(id_orden) FROM rendimiento WHERE id_orden = ' . $data['id_orden'];
        $exist = $localConnection->goQuery($sql);

        if ($exist > 0) {
            // $sql = "INSERT INTO rendimiento (id_orden, id_insumo, " . $campo_empleado . ", " . $campo_valor . ") VALUES (" . $data["id_orden"] . ", " . $data["id_insumo"] . ", " . $data["id_empleado"] . ", " . $data["valor"] . ");";
            $sql = 'INSERT INTO rendimiento (id_orden, ' . $campo_empleado . ', ' . $campo_valor . ') VALUES (' . $data['id_orden'] . ', ' . $data['id_empleado'] . ', ' . $data['valor'] . ');';
        } else {
            $sql = 'UPDATE rendimiento SET ' . $campo_empleado . ' = ' . $data['id_empleado'] . ', ' . $campo_valor . ' = ' . $data['valor'] . ' WHERE id_orden = ' . $data['id_orden'] . ';';
        }

        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Control de de estado del proceso de produccion del empleado
    $app->post('/empleados/registrar-paso/{tipo}/{departamento}/{id_lotes_detalles}/{unidades}', function (Request $request, Response $response, array $args) {
        // PREPARAR FECHAS
        $localConnection = new LocalDB();
        $myDate = new CustomTime();
        $now = $myDate->today();
        $sql = '';
        $object['departamento'] = $args['departamento'];
        $object['tipo'] = $args['tipo'];

        // REGISTRAR EL PASO ACTUAL EN lotes
        $sql = 'SELECT id_orden FROM lotes_detalles WHERE _id = ' . $args['id_lotes_detalles'] . ';';
        $object['sql_total_pendientes'] = $sql;
        $object['id_orden'] = $localConnection->goQuery($sql)[0]['id_orden'];

        if ($args['tipo'] === 'inicio') {
            $campo = 'fecha_inicio';
            $progreso = 'en curso';

            $sqln = "UPDATE lotes SET paso = '" . $args['departamento'] . "' WHERE id_orden = " . $object['id_orden'];
            $object['sql_update_lotes'] = $sqln;
            $object['response_update'] = $localConnection->goQuery($sqln);
        }

        if ($args['tipo'] === 'fin') {
            $sqle = 'SELECT unidades_solicitadas unidades, id_empleado FROM lotes_detalles WHERE _id = ' . $args['id_lotes_detalles'];
            $respLotesDetalles = $localConnection->goQuery($sqle);

            $object['resp'] = $respLotesDetalles;

            /* if ($args["departamento"] === "Costura") {
              $sqlpr = "SELECT id_woo FROM lotes_detalles WHERE _id = " . $args["id_lotes_detalles"];
              $res_lotes_detalles = $localConnection->goQuery($sqlpr)[0]["id_woo"];

              $id_prod = intval($res_lotes_detalles);
              $woo = new WooMe();
              $prod_woo = $woo->getProductById($id_prod);
              // $object["product_woo"] = $prod_woo;

              $object["product-attributes"] = $prod_woo->attributes;
              if (empty($prod_woo->attributes)) {
                $monto_pago = 0;
                $object["product-attributes-vacio"] = true;
              } else {
                $object["product-attributes-vacio"] = false;
                $object["procesar_pago"]["unidades"] = $args["unidades"];
                $object["procesar_pago"]["comison_woo"] = floatval($prod_woo->attributes[0]->options[0]);
                $calculo_pago = intval($args["unidades"]) * floatval($prod_woo->attributes[0]->options[0]);
                $monto_pago = number_format($calculo_pago, 2);
                $object["procesar_pago"]["monto_pago"] = $monto_pago;
              }
            } */
            if ($args['departamento'] === 'Costura') {
                $sql_comision = 'SELECT sys_comision_de_costura tipo FROM config';
                $tipo_comision = $localConnection->goQuery($sql_comision)[0]['tipo'];
                // $tipo_comision = $tmp_comision["tipo"];

                if ($tipo_comision === 'producto') {
                    $sqlc = 'SELECT b.comision FROM lotes_detalles a JOIN products b ON b._id = a.id_woo WHERE a._id = ' . $args['id_lotes_detalles'];
                } else {
                    $sqlc = 'SELECT comision FROM empleados WHERE _id = ' . $respLotesDetalles[0]['id_empleado'];
                }
            } else {
                $sqlc = 'SELECT comision FROM empleados WHERE _id = ' . $respLotesDetalles[0]['id_empleado'];
            }
            // CALCULAR MONTO DEL PAGO

            // $sqlc = "SELECT comision FROM empleados WHERE _id = " . $respLotesDetalles[0]["id_empleado"];
            $comisionEmpleado = $localConnection->goQuery($sqlc);
            $object['comision'] = $respLotesDetalles;

            $calculo_pago = floatval($comisionEmpleado[0]['comision']) * floatval($args['unidades']);

            // $monto_pago = number_format($calculo_pago, 2);
            $monto_pago = $calculo_pago;
            $object['monto_pago'] = $monto_pago;

            // GUARDAR PAGO
            $sqlxxx = 'SELECT id_empleado FROM lotes_detalles WHERE _id = ' . $args['id_lotes_detalles'];
            $miEmpleado = $localConnection->goQuery($sqlxxx);

            $sql .= 'INSERT INTO pagos(id_orden, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (' . $object['id_orden'] . ', ' . $args['unidades'] . ', ' . $args['id_lotes_detalles'] . ", 'aprobado' , " . $monto_pago . ', ' . $miEmpleado[0]['id_empleado'] . ", '" . $args['departamento'] . "');";
            $campo = 'fecha_terminado';
            $progreso = 'terminada';
        }

        // ACTUALIZAR DATOS DE INICIO DE TAREA
        $sql .= 'UPDATE lotes_detalles SET ' . $campo . " = '" . $now . "', progreso = '" . $progreso . "' WHERE _id = " . $args['id_lotes_detalles'];
        $object['sql'] = $sql;
        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->post('/empleados/registrar-paso-por-lotes/{departamento}', function (Request $request, Response $response, array $args) {
        // OBTENER DATOS VIA POST
        $misTareas = $request->getParsedBody();
        $object['request'] = json_decode($misTareas['item']);
        $object['args'] = $args;
        $localConnection = new LocalDB();

        $myDate = new CustomTime();
        $now = $myDate->today();
        $sql = '';
        $tipo_fecha = '';
        $progreso = '';

        foreach ($object['request'] as $key => $value) {
            $object['foreach'][$key] = $value->id_lotes_detalles;
            $id_orden = $value->id_orden;

            $object['progreso'] = $value->progreso;
            if ($value->progreso === 'por iniciar') {
                $tipo_fecha = 'fecha_inicio';
                $progreso = 'en curso';
                $sql .= "UPDATE lotes SET paso = '" . $args['departamento'] . "' WHERE id_orden = " . $id_orden . ';';
            } else if ($value->progreso === 'en curso') {
                $tipo_fecha = 'fecha_terminado';
                $progreso = 'terminado';
                $sql .= "UPDATE lotes SET paso = '" . $args['departamento'] . "' WHERE id_orden = " . $id_orden . ';';

                $sqle = 'SELECT unidades_solicitadas unidades, id_empleado FROM lotes_detalles WHERE _id = ' . $value->id_lotes_detalles;
                $respLotesDetalles = $localConnection->goQuery($sqle);

                if ($args['departamento'] === 'Costura') {
                    $sqlpr = 'SELECT id_woo FROM lotes_detalles WHERE _id = ' . $value->id_lotes_detalles;
                    $res_lotes_detalles = $localConnection->goQuery($sqlpr)[0]['id_woo'];

                    $id_prod = intval($res_lotes_detalles);
                    $woo = new WooMe();
                    $prod_woo = $woo->getProductById($id_prod);
                    $object['product_woo'] = $prod_woo;

                    // $object["product-attributes"] = $prod_woo->attributes;
                    if (empty($prod_woo->attributes)) {
                        $monto_pago = 0;
                        $object['product-attributes-vacio'] = true;
                    } else {
                        $object['product-attributes-vacio'] = false;
                        $object['procesar_pago']['unidades'] = $respLotesDetalles[0]['unidades'];
                        $object['procesar_pago']['comison_woo'] = floatval($prod_woo->attributes[0]->options[0]);
                        $calculo_pago = intval($respLotesDetalles[0]['unidades']) * floatval($prod_woo->attributes[0]->options[0]);
                        $monto_pago = number_format($calculo_pago, 2);
                        $object['procesar_pago']['monto_pago'] = $monto_pago;
                    }
                } else {
                    // CALCULAR MONTO DEL PAGO

                    $sqlc = 'SELECT comision FROM empleados WHERE _id = ' . $respLotesDetalles[0]['id_empleado'];
                    $comisionEmpleado = $localConnection->goQuery($sqlc);
                    $object['comision'] = $respLotesDetalles;

                    $calculo_pago = floatval($comisionEmpleado[0]['comision']) * floatval($respLotesDetalles[0]['unidades']);
                    // $monto_pago = number_format($calculo_pago, 2);
                    $monto_pago = $calculo_pago;
                    $object['monto_pago'] = $monto_pago;
                }

                // GUARDAR PAGO
                $sqlxxx = 'SELECT id_empleado FROM lotes_detalles WHERE _id = ' . $value->id_lotes_detalles;
                $miEmpleado = $localConnection->goQuery($sqlxxx);

                $sql .= 'INSERT INTO pagos(id_orden, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (' . $id_orden . ', ' . $respLotesDetalles[0]['unidades'] . ', ' . $value->id_lotes_detalles . ", 'aprobado' , " . $monto_pago . ', ' . $miEmpleado[0]['id_empleado'] . ", '" . $args['departamento'] . "');";
                $tipo_fecha = 'fecha_terminado';
                $progreso = 'terminada';
            }

            $sql .= 'UPDATE lotes_detalles SET ' . $tipo_fecha . " = '" . $now . "', progreso = '" . $progreso . "' WHERE _id = " . $value->id_lotes_detalles . ';';
        }

        $object['sql'] = $sql;
        $result_sql = $localConnection->goQuery($sql);
        $object['result_sql'] = $result_sql;

        $localConnection->disconnect();

        // $object["goQuery_response"] = $localConnection->goQuery($sql);

        /* foreach ($object["request"] as $key => $value) {
            $id_lotes_detalles = $value->id_lotes_detalles;
// PREPARAR FECHAS
            $myDate = new CustomTime();
            $now = $myDate->today();
            $sql = "";
            $object["departamento"] = $args["departamento"];
            $object["tipo"] = $args["tipo"];
// REGISTRAR EL PASO ACTUAL EN lotes
            $id_orden = $value->id_orden;
            if ($args["tipo"] === "inicio") {
                $campo = "fecha_inicio";
                $progreso = "en curso";
                $sqln = "UPDATE lotes SET paso = '" . $args["departamento"] . "' WHERE id_orden = " . $id_orden;
                $object["sql_update_lotes"] = $sqln;
                $object["response_update"] = $localConnection->goQuery($sqln);
            }
            if ($args["tipo"] === "fin") {
                $sqle = "SELECT unidades_solicitadas unidades, id_empleado FROM lotes_detalles WHERE _id = " . $id_lotes_detalles;
                $respLotesDetalles = $localConnection->goQuery($sqle);
                $object["resp"] = $respLotesDetalles;
                if ($args["departamento"] === "Costura") {
                    $sqlpr = "SELECT id_woo FROM lotes_detalles WHERE _id = " . $id_lotes_detalles;
                    $res_lotes_detalles = $localConnection->goQuery($sqlpr)[0]["id_woo"];
                    $id_prod = intval($res_lotes_detalles);
                    $woo = new WooMe();
                    $prod_woo = $woo->getProductById($id_prod);
// $object["product_woo"] = $prod_woo;
                    $object["product-attributes"] = $prod_woo->attributes;
                    if (empty($prod_woo->attributes)) {
                        $monto_pago = 0;
                        $object["product-attributes-vacio"] = true;
                        } else {
                            $object["product-attributes-vacio"] = false;
                            $object["procesar_pago"]["unidades"] = $respLotesDetalles[0]["unidades"];
                            $object["procesar_pago"]["comison_woo"] = floatval($prod_woo->attributes[0]->options[0]);
                            $calculo_pago = intval($respLotesDetalles[0]["unidades"]) * floatval($prod_woo->attributes[0]->options[0]);
                            $monto_pago = number_format($calculo_pago, 2);
                            $object["procesar_pago"]["monto_pago"] = $monto_pago;
                        }
                        } else {
// CALCULAR MONTO DEL PAGO
                            $sqlc = "SELECT comision FROM empleados WHERE _id = " . $respLotesDetalles[0]["id_empleado"];
                            $comisionEmpleado = $localConnection->goQuery($sqlc);
                            $object["comision"] = $respLotesDetalles;
                            $calculo_pago = floatval($comisionEmpleado[0]["comision"]) * floatval($respLotesDetalles[0]["unidades"]);
// $monto_pago = number_format($calculo_pago, 2);
                            $monto_pago = $calculo_pago;
                            $object["monto_pago"] = $monto_pago;
                        }
// GUARDAR PAGO
                        $sqlxxx = "SELECT id_empleado FROM lotes_detalles WHERE _id = " . $id_lotes_detalles;
                        $miEmpleado = $localConnection->goQuery($sqlxxx);
                        $sql .= "INSERT INTO pagos(id_orden, cantidad, id_lotes_detalles, estatus, monto_pago, id_empleado, detalle) VALUES (" . $id_orden . ", " . $respLotesDetalles[0]["unidades"] . ", " . $id_lotes_detalles . ", 'aprobado' , " . $monto_pago . ", " . $miEmpleado[0]["id_empleado"] . ", '" . $args["departamento"] . "');";
                        $campo = "fecha_terminado";
                        $progreso = "terminada";
                    }
// ACTUALIZAR DATOS DE INICIO DE TAREA
                    $sql .= "UPDATE lotes_detalles SET " . $campo . " = '" . $now . "', progreso = '" . $progreso . "' WHERE _id = " . $id_lotes_detalles;
                    $object['items'] = $localConnection->goQuery($sql);
                } */

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Resgistrar pago del empleado en el momento que indica que ha terminado su tarea
    $app->get('/empleados/registrar-pago/{id_lotes_detalles}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT id_empleado FROM lotes_detalles WHERE _id = ' . $args['id_lotes_detalles'];
        $miEmpleado = $localConnection->goQuery($sql);

        $sql = 'INSERT INTO pagos(id_lotes_detalles, estatus, id_empleado) VALUES (' . $args['id_lotes_detalles'] . ", 'aprobado', " . $miEmpleado['id_empleado'] . ')';
        $object['sql'] = $sql;
        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener ordenes asociadas a los empleados
    $app->get('/empleados/ordenes-asignadas/v1/{id_empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT c.prioridad, a.id_orden, b.unidades_solicitadas, b.unidades_solicitadas piezas_actuales, b.fecha_inicio, b.fecha_terminado, b._id id_lotes_detalles, b.departamento, a.id_woo, a._id id_ordenes_productos, a.name producto, b.id_empleado, a.talla, a.corte, a.tela, b.departamento, b.progreso, b.detalles detalles_revision FROM ordenes_productos a JOIN lotes_detalles b ON a._id = b.id_ordenes_productos LEFT JOIN lotes c ON c.id_orden = b.id_orden WHERE b.id_empleado = ' . $args['id_empleado'] . " AND b.progreso NOT LIKE 'terminada' ORDER BY c.prioridad DESC , b.progreso ASC, b.id_orden ASC";

        $items = $localConnection->goQuery($sql);
        $object['ordenes'] = $items;

        /* $sql = "SELECT a.id_orden orden, a.id_woo, b.name producto,  a.unidades_solicitadas unidades, a.unidades_solicitadas piezas_actuales, b.talla talla, b.corte, b.tela FROM lotes_detalles a JOIN ordenes_productos b ON a.id_ordenes_productos = b._id WHERE id_empleado = " . $args['id_empleado'] . " AND progreso = 'en curso'";
            $object['trabajos_en_curso'] = $localConnection->goQuery($sql); */

        // BUSCAR PAGOS EXISTENTES PARA LOS REGISTROS ENCONTRADOS EN EL PASO ANTERIOR
        $object['pagos'] = [];
        if (empty($ordenes)) {
            $object['pagos'] = [];
        } else {
            foreach ($ordenes as $key => $item_lote) {
                $sqlx = 'SELECT id_lotes_detalles, monto_pago, estatus, fecha_pago FROM pagos WHERE id_lotes_detalles = ' . $item_lote['id_lotes_detalles'];
                $tmpPago = $localConnection->goQuery($sqlx);

                if (!empty($tmpPago)) {
                    $object['pagos'][] = $tmpPago;
                }
            }
        }

        $object['fields'][0]['key'] = 'nombre';
        $object['fields'][0]['label'] = 'Nombre';
        $object['fields'][1]['key'] = 'username';
        $object['fields'][1]['label'] = 'Usuario';
        $object['fields'][2]['key'] = 'departamento';
        $object['fields'][2]['label'] = 'Departamento';
        $object['fields'][3]['key'] = 'acciones';
        $object['fields'][3]['label'] = 'Acciones';

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener ordenes asociadas a los empleados V2
    $app->get('/empleados/ordenes-asignadas/v2/{id_empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        // ordenes
        $sql = 'SELECT DISTINCT 
        (SELECT COUNT(_id) FROM inventario_movimientos WHERE id_orden = a.id_orden AND id_empleado = b.id_empleado) AS extra,
        (SELECT COUNT(_id) FROM reposiciones WHERE id_orden = a.id_orden AND id_empleado = b.id_empleado AND id_ordenes_productos = a._id AND terminada = 0) AS en_reposiciones,
        (SELECT COUNT(_id) FROM tintas WHERE id_orden = a.id_orden) AS en_tintas,
        (SELECT COUNT(_id) FROM inventario_movimientos WHERE id_orden = a.id_orden AND id_empleado = ' . $args['id_empleado'] . ") AS en_inv_mov,
        (SELECT valor_inicial FROM inventario_movimientos WHERE id_orden = a.id_orden AND departamento = 'Impresión' LIMIT 1) AS valor_inicial,
        (SELECT valor_final FROM inventario_movimientos WHERE id_orden = a.id_orden AND departamento = 'Impresión' LIMIT 1) AS valor_final,
        c.prioridad,
        z.unidades_produccion AS unidades_solicitadas,
        a.cantidad AS unidades,
        a.cantidad AS piezas_actuales,
        b.fecha_inicio,
        b.fecha_terminado,
        DATE_FORMAT(d.fecha_entrega, '%d-%m-%Y') AS fecha_entrega,
        b._id AS id_lotes_detalles,
        b.departamento,
        a.id_orden,
        a.id_orden AS orden,
        a.id_woo,
        d.observaciones,
        br.borrador detalle_empleado,
        a._id AS id_ordenes_productos,
        a.name AS producto,
        b.id_empleado,
        x.detalle detalle_reposicion,
        a.talla,
        a.corte,
        a.tela,
        b.departamento,
        c.prioridad,
        c.paso,
        d.status,
        b.progreso,
        b.detalles AS detalles_revision
        FROM
        ordenes_productos a
        JOIN lotes_detalles b ON a._id = b.id_ordenes_productos
        JOIN ordenes d ON a.id_orden = d._id
        LEFT JOIN lotes c ON c.id_orden = b.id_orden
        LEFT JOIN lotes_historico_solicitadas z ON z.id_orden = a.id_orden
        -- LEFT JOIN inventario_movimientos e ON c.id_orden = e.id_orden
        LEFT JOIN products p ON p._id = a.id_woo
        LEFT JOIN reposiciones x ON x.id_orden = d._id AND x.id_empleado = b.id_empleado AND x.id_ordenes_productos = a._id
        LEFT JOIN ordenes_borrador_empleado br ON br.id_orden = b.id_orden AND br.id_empleado = b.id_empleado
        WHERE  
        (b.id_empleado = " . $args['id_empleado'] . ")
        AND (d.status LIKE 'En espera' OR d.status LIKE 'activa')
        AND p.fisico = 1
        ORDER BY
        c.prioridad DESC,
        b.progreso ASC,
        b.id_orden ASC;
    ";
        $object['ordenes_sql'] = $sql;
        $object['ordenes'] = $localConnection->goQuery($sql);

        // ORDENES VINCULADAS
        $sql = "SELECT
        a._id,
        a.id_child,
        a.id_father
    FROM
        ordenes_vinculadas a 
    LEFT JOIN ordenes b ON b._id = a.id_father
    WHERE b.status NOT LIKE 'pausada' OR b.status NOT LIKE 'cancelada' OR b.status NOT LIKE 'terminada'
    ORDER BY
        id_father ASC
    ";
        $object['vinculadas'] = $localConnection->goQuery($sql);

        // Deetalles de los productos
        $sql = 'SELECT DISTINCT
            a._id id_ordenes_productos,
            b.id_orden,
            r.terminada reposicion_terminada,
            b._id id_lotes_detalles,
            b.terminado,
            a.name,
            d.unidades_produccion cantidad_corte,
            a.cantidad,
            r.unidades unidades_reposicion,
            r.detalle detalle_reposicion,
            a.talla,
            a.corte,
            a.tela
        FROM
            ordenes_productos a
        LEFT JOIN 
            lotes_detalles b ON a._id = b.id_ordenes_productos
        LEFT JOIN ordenes c ON c._id = b.id_orden 
        LEFT JOIN lotes_historico_solicitadas d ON d.id_orden = a.id_orden
        LEFT JOIN reposiciones r ON r.id_ordenes_productos = a._id AND r.id_empleado
        WHERE
            b.id_empleado = ' . $args['id_empleado'] . " AND (c.status LIKE 'En espera' OR c.status LIKE 'activa') 
    ORDER BY b.id_orden ASC";
        $object['productos'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        // $response->getBody()->write(json_encode($object));
        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    // SSE Obtener ordenes asociadas a los empleados via SSE
    $app->get('/sse/empleados/ordenes-asignadas/{id_empleado}', function (Request $request, Response $response, array $args) {
        $sql = "SELECT 
            c.prioridad, 
            a.cantidad unidades_solicitadas, 
            a.cantidad unidades, 
            a.cantidad piezas_actuales, 
            b.fecha_inicio, 
            b.fecha_terminado, 
            DATE_FORMAT(d.fecha_entrega, '%d-%m-%Y') AS fecha_entrega,
            b._id id_lotes_detalles, 
            b.departamento, 
            a.id_orden, 
            a.id_orden orden, 
            a.id_woo, 
            a._id id_ordenes_productos, 
            a.name producto, 
            b.id_empleado, 
            a.talla, 
            a.corte, 
            a.tela, 
            b.departamento, 
            c.prioridad,
            c.paso,
            d.status,
            b.progreso, 
            b.detalles detalles_revision 
            FROM ordenes_productos a 
            JOIN lotes_detalles b 
            ON a._id = b.id_ordenes_productos 
            JOIN ordenes d ON a.id_orden = d._id
            LEFT JOIN lotes c 
            ON c.id_orden = b.id_orden 
            WHERE (b.id_empleado = " . $args['id_empleado'] . " AND b.progreso NOT LIKE 'terminada') AND (d.status LIKE 'En espera' OR d.status LIKE 'activa') ORDER BY c.prioridad DESC, b.progreso ASC, b.id_orden ASC
        ";
        $obj[0]['sql'] = $sql;
        $obj[0]['name'] = 'items';

        $sql = 'SELECT a._id id_lotes_detalles, a.id_orden orden, a.id_woo, b.name producto,  a.unidades_solicitadas unidades, a.unidades_solicitadas piezas_actuales, b.talla talla, b.corte, b.tela FROM lotes_detalles a JOIN ordenes_productos b ON a.id_ordenes_productos = b._id WHERE id_empleado = ' . $args['id_empleado'] . " AND progreso = 'en curso'";
        $sql = "SELECT 
            c.prioridad, 
            a.cantidad unidades_solicitadas, 
            a.cantidad unidades, 
            a.cantidad piezas_actuales, 
            b.fecha_inicio, 
            b.fecha_terminado, 
            DATE_FORMAT(d.fecha_entrega, '%d-%m-%Y') AS fecha_entrega,
            b._id id_lotes_detalles, 
            b.departamento, 
            a.id_orden, 
            a.id_orden orden, 
            a.id_woo, 
            a._id id_ordenes_productos, 
            a.name producto, 
            b.id_empleado, 
            a.talla, 
            a.corte, 
            a.tela, 
            b.departamento, 
            c.prioridad, 
            b.progreso, 
            b.detalles detalles_revision 
            FROM ordenes_productos a 
            JOIN lotes_detalles b 
            ON a._id = b.id_ordenes_productos 
            JOIN ordenes d ON a.id_orden = d._id
            LEFT JOIN lotes c 
            ON c.id_orden = b.id_orden 
            WHERE b.id_empleado = " . $args['id_empleado'] . " AND b.progreso = 'en curso'
        ";

        // $object['sql_en_curso'] = $sql;
        // $object['trabajos_en_curso'] = $localConnection->goQuery();

        $obj[1]['sql'] = $sql;
        $obj[1]['name'] = 'trabajos_en_curso';

        // BUSCAR ORDENES ACTIVAS ASIGNADAS AL EMPLEADO
        $sql = "SELECT DISTINCT a.id_orden FROM lotes_detalles a JOIN ordenes b ON b._id = a.id_orden WHERE (a.id_empleado = 24 AND a.progreso NOT LIKE 'terminada') AND (b.status LIKE 'En espera' OR b.status LIKE 'activa') ORDER BY a.id_orden ASC";
        $obj[2]['sql'] = $sql;
        $obj[2]['name'] = 'ordenes_asignadas';

        $sql = 'SELECT COUNT(_id) FROM ';

        $sse = new SSE($obj);
        $sse->SsePrint();

        $object['fields'][0]['key'] = 'nombre';
        $object['fields'][0]['label'] = 'Nombre';
        $object['fields'][1]['key'] = 'username';
        $object['fields'][1]['label'] = 'Usuario';
        $object['fields'][2]['key'] = 'departamento';
        $object['fields'][2]['label'] = 'Departamento';
        $object['fields'][3]['key'] = 'acciones';
        $object['fields'][3]['label'] = 'Acciones';

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener todos los empleados
    $app->get('/empleados', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $sql = 'SELECT _id, _id acciones, username, password, nombre, email, departamento, comision, acceso FROM empleados ORDER BY nombre ASC';
        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $object['fields'][0]['key'] = 'nombre';
        $object['fields'][0]['label'] = 'Nombre';
        $object['fields'][1]['key'] = 'username';
        $object['fields'][1]['label'] = 'Usuario';
        $object['fields'][2]['key'] = 'departamento';
        $object['fields'][2]['label'] = 'Departamento';
        $object['fields'][3]['key'] = 'acciones';
        $object['fields'][3]['label'] = 'Acciones';

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Nuevo Empleado
    $app->post('/empleados/nuevo', function (Request $request, Response $response) {
        $miEmpleado = $request->getParsedBody();
        $localConnection = new LocalDB();

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        // Crear estructura de valores para insertar nuevo cliente
        $values = '(';
        $values .= "'" . $now . "',";
        $values .= "'" . $miEmpleado['acceso'] . "',";
        $values .= "'" . $miEmpleado['departamento'] . "',";
        $values .= "'" . $miEmpleado['email'] . "',";
        $values .= "'" . $miEmpleado['nombre'] . "',";
        $values .= "'" . $miEmpleado['password'] . "',";
        $values .= "'" . $miEmpleado['username'] . "')";

        $sql = 'INSERT INTO empleados (`moment`, `acceso`, `departamento`, `email`, `nombre`, `password`, `username`) VALUES ' . $values;
        $object['sql'] = $sql;

        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Elditar Empleados
    $app->post('/empleados/editar', function (Request $request, Response $response) {
        $miEmpleado = $request->getParsedBody();
        $localConnection = new LocalDB();

        // Crear estructura de valores para insertar nuevo cliente
        $values = "username='" . $miEmpleado['username'] . "',";
        $values .= "nombre='" . $miEmpleado['nombre'] . "',";
        $values .= "departamento='" . $miEmpleado['departamento'] . "',";
        $values .= "acceso='" . $miEmpleado['acceso'] . "',";
        $values .= "password='" . $miEmpleado['password'] . "',";
        $values .= "email='" . $miEmpleado['email'] . "',";
        $values .= "comision='" . $miEmpleado['comision'] . "'";

        $sql = 'UPDATE empleados SET ' . $values . ' WHERE _id = ' . $miEmpleado['_id'];
        $object['sql'] = $sql;
        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Eliminar Empleados
    $app->post('/empleados/eliminar', function (Request $request, Response $response) {
        $miEmpleado = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = 'DELETE FROM lotes_detalles WHERE id_empleado = ' . $miEmpleado['id'] . '; DELETE FROM empleados WHERE _id =  ' . $miEmpleado['id'];
        $object['sql'] = $sql;
        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Obtener empelados de produccion y diseño y los demas tambien...
    $app->get('/empleados/produccion/asignacion', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $sql = 'SELECT _id, username, nombre, comision, departamento FROM empleados ORDER BY nombre ASC';
        $object['response'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object['response']));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    /** Fin Empleados */

    /** INSUMOS */

    // OBTENER TODOS LOS INSUMOS
    $app->get('/insumos', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT * FROM inventario ORDER BY insumo ASC';
        $object = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER DETALLES DEL INSUMO
    $app->get('/insumos/{id_insumo}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT * FROM inventario WHERE _id = ' . $args['id_insumo'];
        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object, JSON_NUMERIC_CHECK));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // NUEVO INSUMO
    $app->post('/insumos/nuevo', function (Request $request, Response $response, $args) {
        $miInsumo = $request->getParsedBody();
        $localConnection = new LocalDB();

        // PREPARAR FECHAS
        $myDate = new CustomTime();
        $now = $myDate->today();

        $values = '(';
        $values .= "'" . $now . "',";
        $values .= "'" . $miInsumo['insumo'] . "',";
        $values .= "'" . $miInsumo['departamento'] . "',";
        $values .= "'" . $miInsumo['unidad'] . "',";
        $values .= "'" . $miInsumo['rendimiento'] . "',";
        $values .= "'" . $miInsumo['costo'] . "',";
        $values .= "'" . $miInsumo['cantidad'] . "')";

        $sql = 'INSERT INTO inventario (moment, insumo, departamento, unidad, rendimiento, costo, cantidad) VALUES ' . $values . ';';
        $object = $localConnection->goQuery($sql);
        $sql = 'SELECT _id last_insert_id FROM inventario ORDER BY _id DESC LIMIT 1;';
        $object = $localConnection->goQuery($sql);

        // Accede al ID del registro insertado
        /* $sql = "SELECT LAST_INSERT_ID() as last_insert_id";
            $object['data'] = json_encode($localConnection->goQuery($sql)); */

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // EDITAR INSUMO
    $app->post('/insumos/editar', function (Request $request, Response $response, $args) {
        $miInsumo = $request->getParsedBody();
        $localConnection = new LocalDB();

        // Crear estructura de valores para insertar nuevo cliente
        $values = "insumo='" . $miInsumo['insumo'] . "',";
        $values .= "unidad='" . $miInsumo['unidad'] . "',";
        $values .= "cantidad='" . $miInsumo['cantidad'] . "',";
        $values .= "rendimiento='" . $miInsumo['rendimiento'] . "',";
        $values .= "costo='" . $miInsumo['costo'] . "',";
        $values .= "departamento='" . $miInsumo['departamento'] . "'";

        $sql = 'UPDATE inventario SET ' . $values . ' WHERE _id = ' . $miInsumo['_id'];
        $object['sql'] = $sql;
        $object['data'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // OBTENER INSUMOS PERTENECIENTES A UNA ORDEN
    // Eliminar Insumos

    $app->post('/insumos/eliminar', function (Request $request, Response $response) {
        $miEmpleado = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = 'DELETE FROM inventario WHERE _id =  ' . $miEmpleado['id'];
        $object['sql'] = $sql;
        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Insumos por empleado
    $app->get('/inventario-movimientos/{id_orden}/{id_empleado}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT * FROM ordenes_productos WHERE id_orden = ' . $args['id_orden'] . ' AND id_empleado = ' . $args['id_empleado'];
        $object['items'] = $localConnection->goQuery($sql);

        $sql = 'SELECT b._id, a._id id_insumo, a.cantidad, a.unidad, a.insumo FROM inventario a JOIN inventario_movimientos b ON a._id = b.id_insumo  WHERE b.id_orden = ' . $args['id_orden'] . ' AND b.id_empleado = ' . $args['id_empleado'];
        $object['movimientos'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Insumos historial por orden (Verificar si se han hecho cambios previamente en el valor de las cantidades)
    $app->get('/inventario/historial/{id_orden}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = 'SELECT id_insumo, valor_inicial, valor_final, departamento FROM inventario_movimientos WHERE id_orden = ' . $args['id_orden'];
        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Crear nuevo insumo asignado a empleados
    $app->post('/inventario-movimientos/nuevo', function (Request $request, Response $response) {
        $miInsumo = $request->getParsedBody();
        $localConnection = new LocalDB();
        $object['body'] = $miInsumo;

        // Verificar existencia del registro
        $sql = 'SELECT _id FROM inventario_movimientos WHERE id_orden = ' . $miInsumo['id_orden'] . ' AND id_empleado = ' . $miInsumo['id_empleado'] . ' AND id_producto = ' . $miInsumo['id_producto'] . ' AND id_insumo = ' . $miInsumo['id_insumo'] . " AND departamento = '" . $miInsumo['departamento'] . "'";
        $object['miinsumo'] = json_encode($localConnection->goQuery($sql));
        // $object['id_insumo'] = $object['miinsumo']->_id;

        if (empty(json_decode($object['miinsumo']))) {
            $sql = 'SELECT cantidad, insumo, unidad FROM inventario WHERE _id = ' . $miInsumo['id_insumo'];
            $cantidad = $localConnection->goQuery($sql);
            $object['cantidad_Recuperada'] = $cantidad;

            // PREPARAR FECHAS
            $myDate = new CustomTime();
            $now = $myDate->today();

            $values = "'" . $now . "',";
            $values .= "'" . $miInsumo['departamento'] . "',";
            // $values .= $miInsumo["id_empleado"] . ",";
            $values .= $miInsumo['id_insumo'] . ',';
            $values .= "'" . $cantidad[0]['cantidad'] . "',";
            $values .= $miInsumo['id_producto'];

            $array_ordenes = explode(',', $miInsumo['ordenes']);

            foreach ($array_ordenes as $key => $value) {
                $sql = 'INSERT INTO inventario_movimientos (moment, departamento, id_empleado, id_insumo, id_orden, valor_inicial, id_producto) VALUES (' . $values . ');';
            }
            $result = json_encode($localConnection->goQuery($sql));

            $sql = '';
            if (count($result) > 0) {
                // UPDATE
            }
            {
                // INSERT
            }

            // $sql = "INSERT INTO inventario_movimientos (moment, departamento, id_empleado, id_insumo, id_orden, valor_inicial, id_producto) VALUES (" . $values . ");";
            $object['sql'] = $sql;
            $object['insert'] = json_encode($localConnection->goQuery($sql));
        }  /*else {
$arrayOrdenes = explode(',', $miInsumo['ordenes'])
$sql = "";
foreach ($arrayOrdenes as $key => $orden) {
$sal .= "UPDATE inventario_movimientos SET id_orden = " $orden . " WHERE id_empleado = " . $miInsumo['id_empleado'];
}

// UPDATE
// $sql = "INSERT INTO inventario_movimientos (moment, departamento, id_empleado, id_insumo, id_orden, valor_inicial, id_producto) VALUES (" . $values . ")";
$ql = "UPDATE inventario_movimientos SET ";
$object["sql"] = $sql;
$object['insert'] = json_encode($localConnection->goQuery($sql));
}*/

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Actualizar cantidad del insumo desde produccion
    $app->post('/inventario-movimientos/piezas-cortadas', function (Request $request, Response $response) {
        $miPieza = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = 'INSERT INTO piezas_cortadas (peso, id_orden, id_inventario, id_ordenes_productos, id_empleado) VALUES (' . $miPieza['peso'] . ', ' . $miPieza['id_orden'] . ', ' . $miPieza['id_inventario'] . ', ' . $miPieza['id_ordenes_productos'] . ', ' . $miPieza['id_empleado'] . ')';
        $object['sql'] = $sql;
        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Actualizar invetario_movimientos desde módulo de empleados
    $app->post('/inventario-movimientos/empleados/update-insumo', function (Request $request, Response $response) {
        $miInsumo = $request->getParsedBody();
        $localConnection = new LocalDB();

        // Verifcar si es reposicion y actualizar el campor `terminada`
        if ($miInsumo['es_reposicion'] == 1) {
            $sql = 'UPDATE reposiciones SET terminada = 1 WHERE id_orden = ' . $miInsumo['id_orden'] . ' AND id_empleado = ' . $miInsumo['id_empleado'];
            // $object["sql_update_reposiones"] = $sql;
            $update_reposiciones = $localConnection->goQuery($sql);
        }

        // buscar cantidad actual del producto
        $sql_check = 'SELECT cantidad FROM inventario WHERE _id = ' . $miInsumo['id_insumo'];
        $object['sql_cantidad_producto'] = $sql_check;
        $cantidad_producto = $localConnection->goQuery($sql_check);
        $object['cantidad_producto'] = $cantidad_producto;

        $cantidad_inicial = floatval($cantidad_producto[0]['cantidad']);
        $object['cantidad_inicial'] = $cantidad_inicial;

        if ($miInsumo['departamento'] === 'Estampado' || $miInsumo['departamento'] === 'Corte') {
            // 1.- Busar rendimiento de la tela
            $sql = 'SELECT rendimiento FROM inventario WHERE _id = ' . $miInsumo['id_insumo'];
            $tmpRendimiento = $localConnection->goQuery($sql);
            $rendimiento = floatval($tmpRendimiento[0]['rendimiento']);

            // 2.- Dividir la cantidad que me llega en metros entre el rendimiento para obtener el resultado en Kilos
            if ($rendimiento != 0) {
                $kilos = floatval($miInsumo['cantidad_consumida']) / $rendimiento;
            } else {
                $kilos = 0;  // O cualquier otro valor que consideres apropiado
            }
            // #.- una vez tengo los kilos se los resto al rollo
            $cantidad_consumida = floatval($cantidad_inicial) - floatval($kilos);  // Cantidad final se refiere a la cantidad del insumo consumido

            $object['cantidad_inicial'] = $cantidad_inicial;
            $object['cantidad_consumida_kilos'] = $kilos;
            // $object["cantidad_consumida"] = $cantidad_consumida;

            // $object["cantidad_previa_del_insumo"] = $miInsumo["cantidad_inicial"];

            $sql = 'UPDATE inventario SET cantidad = ' . $cantidad_consumida . ' WHERE _id = ' . $miInsumo['id_insumo'] . ';';
            $sql .= 'SELECT cantidad FROM inventario WHERE _id = ' . $miInsumo['id_insumo'] . ';';
            $update_cantidad_inventario = $localConnection->goQuery($sql);
            $object['update_cantidad_invrntario_SQL'] = $sql;
            $object['update_cantidad_inventario_RSP'] = $update_cantidad_inventario;
        } else {
            $cantidad_consumida = floatval($cantidad_inicial) - floatval($miInsumo['cantidad_consumida']);
            $object['cantidad_consumida'] = $cantidad_consumida;
            $sql = 'UPDATE inventario SET cantidad = ' . $cantidad_consumida . ' WHERE _id = ' . $miInsumo['id_insumo'] . ';';
            $object['resp_update_cantidad'] = $localConnection->goQuery($sql);
        }


        $sql = 'INSERT INTO inventario_movimientos 
            (
             id_orden, 
             id_empleado, 
             id_producto, 
             id_insumo, 
             departamento, 
             valor_inicial, 
             valor_final)
            VALUES (
                    ' . $miInsumo['id_orden'] . ',
                    ' . $miInsumo['id_empleado'] . ',
                    ' . $miInsumo['id_producto'] . ',
                    ' . $miInsumo['id_insumo'] . ",
                    '" . $miInsumo['departamento'] . "',
                    " . $cantidad_inicial . ',
                    ' . $cantidad_consumida . '
                    );
                    ';
        $object['sql_inventario_movimientos'] = $sql;
        $object['resp_invetario_movimientos'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Actualizar cantidad del insumo desde produccion
    $app->post('/inventario-movimientos/update-insumo', function (Request $request, Response $response) {
        $miInsumo = $request->getParsedBody();
        $localConnection = new LocalDB();

        $arrayOrdenes = explode(',', $miInsumo['ordenes']);
        $object['count_Array'] = count($arrayOrdenes);
        $sql = "UPDATE inventario SET cantidad = '" . $miInsumo['cantidad_final'] . "' WHERE _id =  " . $miInsumo['id_insumo'] . ';';

        foreach ($arrayOrdenes as $key => $orden) {
            $sql_check = 'SELECT _id existe FROM inventario_movimientos WHERE id_orden = ' . $orden . ' AND id_empleado = ' . $miInsumo['id_empleado'] . ' AND id_insumo = ' . $miInsumo['id_insumo'] . " AND departamento = '" . $miInsumo['departamento'] . "';";
            $respuesta = $localConnection->goQuery($sql_check);
            $object['respuesta_check'][$key] = $respuesta;

            if (count($respuesta) > 0) {
                $sql .= '
            UPDATE inventario_movimientos 
            SET id_orden = ' . $orden . ', 
            id_empleado = ' . $miInsumo['id_empleado'] . ', 
            id_insumo = ' . $miInsumo['id_insumo'] . ", 
            departamento = '" . $miInsumo['departamento'] . "', 
            valor_inicial = " . $miInsumo['cantidad_inicial'] . ', 
            valor_final = ' . $miInsumo['cantidad_final'] . ' 
            WHERE id_orden = ' . $orden . ' AND id_empleado = ' . $miInsumo['id_empleado'] . ' AND id_insumo = ' . $miInsumo['id_insumo'] . " AND departamento = '" . $miInsumo['departamento'] . "';";
            } else {
                $sql .= '
            INSERT INTO inventario_movimientos 
            (
             id_orden, 
             id_empleado, 
             id_insumo, 
             departamento, 
             valor_inicial, 
             valor_final)
            VALUES (
                    ' . $orden . ',
                    ' . $miInsumo['id_empleado'] . ',
                    ' . $miInsumo['id_insumo'] . ",
                    '" . $miInsumo['departamento'] . "',
                    " . $miInsumo['cantidad_inicial'] . ',
                    ' . $miInsumo['cantidad_final'] . '
                    );
            ';
            }
        }

        $object['sql'] = $sql;
        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Actualizar prioridad del lote
    $app->post('/inventario-movimientos/update-prioridad', function (Request $request, Response $response) {
        $prioridad = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = 'UPDATE lotes SET prioridad = ' . $prioridad['prioridad'] . ' WHERE id_orden = ' . $prioridad['id'];
        $object['sql'] = $sql;
        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Eliminar insumo asignado
    $app->post('/inventario-movimientos/eliminar', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = 'DELETE FROM `inventario_movimientos` WHERE _id = ' . $data['id'];
        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Reporte de insumos por número de orden
    $app->get('/insumos/reporte/orden/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();
        $sql = "SELECT b._id id_insumo, a.id_orden,  b.insumo, a.valor_inicial, a.valor_final, a.id_producto, DATE_FORMAT(a.moment, '%d/%m/%Y') moment FROM inventario_movimientos a JOIN inventario b ON a.id_insumo = b._id WHERE a.id_orden = " . $args['id'] . ' ORDER BY a.id_producto';

        $object['sql'] = $sql;

        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $object['fields'][0]['key'] = 'id_insumo';
        $object['fields'][0]['label'] = 'ID';
        $object['fields'][0]['sortable'] = true;

        $object['fields'][1]['key'] = 'insumo';
        $object['fields'][1]['label'] = 'Insumo';
        $object['fields'][1]['sortable'] = true;

        $object['fields'][2]['key'] = 'valor_inicial';
        $object['fields'][2]['label'] = 'Valor Inicial';
        // $object['fields'][1]['sortable'] = true;
        $object['fields'][3]['key'] = 'valor_final';
        $object['fields'][3]['label'] = 'Valor Final';
        // $object['fields'][2]['sortable'] = true;
        $object['fields'][4]['key'] = 'id_producto';
        $object['fields'][4]['label'] = 'Producto';
        $object['fields'][4]['sortable'] = true;

        $object['fields'][4]['key'] = 'moment';
        $object['fields'][4]['label'] = 'Fecha';
        $object['fields'][4]['sortable'] = true;

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // Reporte de insumos por insumo
    $app->get('/insumos/reporte/insumos/{id}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = "SELECT a.id_orden, b.nombre, c.insumo, a.valor_inicial, a.valor_final, DATE_FORMAT(a.moment, '%d/%m/%Y') moment FROM inventario_movimientos a JOIN empleados b ON a.id_empleado = b._id JOIN inventario c ON a.id_insumo = c._id WHERE a.id_insumo =" . $args['id'] . ' ORDER BY c.insumo';
        $object['sql'] = $sql;
        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $object['fields'][0]['key'] = 'id_orden';
        $object['fields'][0]['label'] = 'Orden';
        $object['fields'][0]['sortable'] = true;

        $object['fields'][1]['key'] = 'valor_inicial';
        $object['fields'][1]['label'] = 'Valor Inicial';
        // $object['fields'][1]['sortable'] = true;
        $object['fields'][2]['key'] = 'valor_final';
        $object['fields'][2]['label'] = 'Valor Final';
        // $object['fields'][2]['sortable'] = true;
        $object['fields'][3]['key'] = 'nombre';
        $object['fields'][3]['label'] = 'Empleado';

        $object['fields'][4]['key'] = 'moment';
        $object['fields'][4]['label'] = 'Fecha';
        $object['fields'][3]['sortable'] = true;

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    // Reporte de insumos por producto
    $app->get('/insumos/reporte/insumos/producto/{id_producto}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $sql = "SELECT
    a.id_orden,
    a.id_woo id_producto,
    a.name producto,
    b.id_insumo,   
    d.insumo,
    b.valor_inicial,
    b.valor_final,   
    c.nombre,
    b.departamento,
    DATE_FORMAT(b.moment, '%d/%m/%Y')moment

    FROM
    ordenes_productos a
    JOIN inventario_movimientos b ON b.id_orden = a.id_orden 
    JOIN inventario d ON b.id_insumo = d._id
    JOIN empleados c ON c._id = b.id_empleado 
    WHERE a.id_woo =" . $args['id_producto'] . ' ORDER BY a.category_name
    ';

        $object['sql'] = $sql;
        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $object['fields'][0]['key'] = 'id_orden';
        $object['fields'][0]['label'] = 'Orden';

        $object['fields'][1]['key'] = 'producto';
        $object['fields'][1]['label'] = 'Producto';

        $object['fields'][2]['key'] = 'id_insumo';
        $object['fields'][2]['label'] = 'ID insumo';

        $object['fields'][3]['key'] = 'insumo';
        $object['fields'][3]['label'] = 'Insumo';
        // $object['fields'][0]['sortable'] = true;
        $object['fields'][4]['key'] = 'valor_inicial';
        $object['fields'][4]['label'] = 'Valor Inicial';
        // $object['fields'][1]['sortable'] = true;
        $object['fields'][5]['key'] = 'valor_final';
        $object['fields'][5]['label'] = 'Valor Final';
        // $object['fields'][2]['sortable'] = true;
        $object['fields'][6]['key'] = 'nombre';
        $object['fields'][6]['label'] = 'Empleado';

        $object['fields'][7]['key'] = 'moment';
        $object['fields'][7]['label'] = 'Fecha';

        $object['fields'][8]['key'] = 'moment';
        $object['fields'][8]['label'] = 'Fecha';
        // $object['fields'][3]['sortable'] = true;

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /** FIN INSUMOS */

    /** INVENTARIO */
    $app->get('/inventario/{departamento}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $object['fields'][5]['label'] = 'ACCIONES';

        if ($args['departamento'] === 'todos') {
            $sql = 'SELECT
          _id,
          _id rollo,
          insumo,
          cantidad cantidad_inicial,
          cantidad cantidad_final,
          cantidad,
          ROUND((rendimiento * cantidad), 2) AS metros,
          unidad,
          costo,
          rendimiento,
          departamento,
          moment
      FROM
          inventario
      ORDER BY
          insumo ASC;';
        } else {
            $sql = "SELECT
          _id,
          _id rollo,
          insumo,
          cantidad cantidad_inicial,
          cantidad cantidad_final,
          cantidad,
          ROUND((rendimiento * cantidad),
          2) AS metros,
          unidad,
          costo,
          rendimiento,
          departamento,
          moment
      FROM
          inventario
      WHERE
          departamento = '" . $args[' departamento '] . "'
      ORDER BY
          insumo ASC;";
        }
        $object['items'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $object['fields'][0]['key'] = 'rollo';
        $object['fields'][0]['label'] = 'Rollo';
        $object['fields'][0]['sortable'] = false;
        $object['fields'][1]['key'] = 'insumo';
        $object['fields'][1]['label'] = 'Nombre';
        $object['fields'][1]['sortable'] = false;
        $object['fields'][2]['key'] = 'rendimiento';
        $object['fields'][2]['label'] = 'Rendimiento';
        $object['fields'][2]['sortable'] = false;
        $object['fields'][3]['key'] = 'costp';
        $object['fields'][3]['label'] = 'Costo';
        $object['fields'][3]['sortable'] = false;
        $object['fields'][4]['key'] = 'departamento';
        $object['fields'][4]['label'] = 'Departamento';
        $object['fields'][4]['sortable'] = true;
        $object['fields'][5]['key'] = 'unidad';
        $object['fields'][5]['label'] = 'Unidad';
        $object['fields'][5]['sortable'] = false;
        $object['fields'][6]['key'] = 'cantidad';
        $object['fields'][6]['label'] = 'Cantidad';
        $object['fields'][6]['sortable'] = false;
        $object['fields'][7]['key'] = '_id';
        $object['fields'][7]['sortable'] = false;

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/inventario/loader/{id}', function (Request $request, Response $response, array $args) {
        $woo = new WooMe();
        // $response->getBody()->write($woo->createCustomerNeneteen());
        $object['result'] = $woo->getProductsInfo($args['id']);

        /* $object['fields'][0]['key'] = "rollo";
            $object['fields'][0]['label'] = "ROLLO";
            $object['fields'][1]['key'] = "insumo";
            $object['fields'][1]['label'] = "NOMBRE";
            $object['fields'][2]['key'] = "departamento";
            $object['fields'][2]['label'] = "DEPARTAMENTO";
            $object['fields'][3]['key'] = "unidad";
            $object['fields'][3]['label'] = "UNIAD";
            $object['fields'][4]['key'] = "cantidad";
            $object['fields'][4]['label'] = "CANTIDAD";
            $object['fields'][5]['key'] = "_id"; */

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /** FIN INVENTARIO */

    /** ASISTENCIAS */

    // Crear nuevo registro en la tabla de asistencias
    $app->post('/asistencias', function (Request $request, Response $response) {
        $data = $request->getParsedBody();
        $localConnection = new LocalDB();

        $sql = 'INSERT INTO `asistencias`(`id_empleado`, `registro`, `moment`) VALUES (' . $data['id_empleado'] . ",'" . $data['registro'] . "','" . $data['moment'] . "')";
        $object['response'] = json_encode($localConnection->goQuery($sql));

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/asistencias/semanal', function (Request $request, Response $response) {
        $localConnection = new LocalDB();

        $sql = "SELECT 
        a._id id_asistencias,
        e._id id_empleado,
        e.nombre AS empleado,
        DATE_FORMAT(a.moment, '%H:%i') AS hora,
        DATE_FORMAT(a.moment, '%d/%m/%Y') AS fecha,
        CASE 
        WHEN DAYOFWEEK(a.moment) = 2 THEN 'L'
        WHEN DAYOFWEEK(a.moment) = 3 THEN 'M'
        WHEN DAYOFWEEK(a.moment) = 4 THEN 'X'
        WHEN DAYOFWEEK(a.moment) = 5 THEN 'J'
        WHEN DAYOFWEEK(a.moment) = 6 THEN 'V'
        WHEN DAYOFWEEK(a.moment) = 7 THEN 'S'
        WHEN DAYOFWEEK(a.moment) = 1 THEN 'D'
        END AS dia,
        CASE 
        WHEN a.registro = 'entrada_manana' THEN 'Entrada mañana'
        WHEN a.registro = 'salida_manana' THEN 'Salida mañana'
        WHEN a.registro = 'entrada_tarde' THEN 'Entrada tarde'
        WHEN a.registro = 'salida_tarde' THEN 'Salida tarde'
        END AS registro
        FROM 
        asistencias a
        JOIN 
        empleados e ON a.id_empleado = e._id
        WHERE 
        YEARWEEK(a.moment) = YEARWEEK(NOW())
        ORDER BY 
        e.nombre ASC,
        a.moment ASC;
        ";
        $object['data_semana'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    // REPORTE DE ASISTENCIAS POR FECHA UNICA
    $app->get('/asistencias/tabla/{fecha}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $object['fields'][0]['key'] = 'nombre';
        $object['fields'][0]['label'] = 'Nombre';
        $object['fields'][1]['key'] = 'moment';
        $object['fields'][1]['label'] = 'Entrada Mañana';
        $object['fields'][2]['key'] = 'moment';
        $object['fields'][2]['label'] = 'Salida Mañana';
        $object['fields'][3]['key'] = 'moment';
        $object['fields'][3]['label'] = 'Entrada Tarde';
        $object['fields'][4]['key'] = 'moment';
        $object['fields'][4]['label'] = 'Salida Tarde';

        // OBTENER TODOS LOS EMPLEADOS
        $sql = 'SELECT * FROM empleados ORDER BY nombre ASC';
        $object['empleados'] = $localConnection->goQuery($sql);

        // TODO las dos variables siguinetes estan mal arreglar esto
        $today = null;
        $date = null;
        // $myDate = new CustomTime();
        // $now = $myDate->today();
        // $fecha = explode(' ', $now); // La fecha la recibimos por args
        $fecha = $args['fecha'];

        // OBTENER ASISTENCIAS DIARIAS
        // $sql = "SELECT a._id id_empleado, b._id id_asistencia, a.nombre, DATE_FORMAT(b.moment, '%h:%i %p') AS hora, DATE_FORMAT(b.moment, '%Y-%m-%d') AS fecha, b.registro, b.detalle FROM empleados a LEFT JOIN asistencias b ON b.id_empleado = a._id WHERE b.moment LIKE '" . $fecha . "%' OR a._id > 0;";
        $sql = "SELECT
        a._id id_empleado,
        b._id id_asistencia,
        a.nombre,
        DATE_FORMAT(b.moment, '%h:%i %p') AS hora,
        DATE_FORMAT(b.moment, '%Y-%m-%d') AS fecha,
        b.registro,
        b.detalle
        FROM
        empleados a
        LEFT JOIN asistencias b ON
        b.id_empleado = a._id
        WHERE
        (a._id > 0  AND b.moment LIKE '" . $fecha . "%') OR (a._id > 0 AND b.moment IS NULL)
         ORDER BY a.nombre ASC;";
        $object['sql_diarias'] = $sql;
        $mod_date = strtotime($date . '+ 0 days');
        $object['diarias'] = $localConnection->goQuery($sql);

        // NUEVO REPORTE
        $sql = 'SELECT a.id_empleado, b.username, a.moment, DATE(a.moment) fecha, UNIX_TIMESTAMP(a.moment) - 3600 timestamp, DAYNAME(a.moment) dia, a.registro FROM asistencias a JOIN empleados b ON a.id_empleado = b._id WHERE WEEK(a.moment) = WEEK(NOW());';

        $today . "%'";
        $object['reporte'] = $localConnection->goQuery($sql);

        // ASISTENCIAS SEMANA
        $today = date('Y-m-d', $mod_date);

        $sql = "SELECT
         b._id,
         b.username empleado
         FROM asistencias a
         JOIN empleados b ON b._id = a.id_empleado
         WHERE WEEK(a.moment) = WEEK('" . $today . "')
                                     GROUP BY b.username
                                     ORDER BY
                                     b.username ASC,
                                     a.moment ASC";
        $today . "%'";

        $object['semana'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });

    $app->get('/asistencias/reporte/resumen/{fecha_inicio}/{fecha_fin}', function (Request $request, Response $response, array $args) {
        $localConnection = new LocalDB();

        $object['fields_resumen'][0]['key'] = 'nombre';
        $object['fields_resumen'][0]['label'] = 'Nombre';
        $object['fields_resumen'][1]['key'] = 'horas_trabajadas';
        $object['fields_resumen'][1]['label'] = 'Horas Trabajadas';
        $object['fields_resumen'][2]['key'] = 'acciones';
        $object['fields_resumen'][2]['label'] = 'Acciones';

        $object['fields_detallado'][0]['key'] = 'nombre';
        $object['fields_detallado'][0]['label'] = 'Nombre';
        $object['fields_detallado'][1]['key'] = 'registro';
        $object['fields_detallado'][1]['label'] = 'Registro';
        $object['fields_detallado'][2]['key'] = 'hora';
        $object['fields_detallado'][2]['label'] = 'Hora';
        $object['fields_detallado'][3]['key'] = 'fecha';
        $object['fields_detallado'][3]['label'] = 'Fecha';

        // REPORTE RESUMEN
        $sql = "SELECT
            a.id_empleado,
            a.id_empleado acciones,
            b.nombre, 
            ROUND(
                  IFNULL(
                         TIMESTAMPDIFF(MINUTE,
                                       MIN(CASE WHEN registro = 'entrada_manana' THEN a.moment END),
                                       MAX(CASE WHEN registro = 'salida_manana' THEN a.moment END)
                                       ) / 60.0,
                         0
                         )
                  +
                  IFNULL(
                         TIMESTAMPDIFF(MINUTE,
                                       MIN(CASE WHEN registro = 'entrada_tarde' THEN a.moment END),
                                       MAX(CASE WHEN registro = 'salida_tarde' THEN a.moment END)
                                       ) / 60.0,
                         0
                         ),
                  2
                  ) AS horas_trabajadas
            FROM asistencias a 
            JOIN empleados b ON b._id = a.id_empleado 
            WHERE DATE(a.moment) BETWEEN '" . $args['fecha_inicio'] . "' AND '" . $args['fecha_fin'] . "'
            GROUP BY a.id_empleado;
            ";
        $object['resumen'] = $localConnection->goQuery($sql);

        // REPORTE DETALLADO
        $sql = "SELECT
            b._id id_empleado,
            b.nombre,
            DATE_FORMAT(a.moment, '%d/%m/%Y') AS fecha,
            DATE_FORMAT(a.moment, '%h:%i %p') AS hora,
            CASE 
            WHEN a.registro = 'entrada_manana' THEN 'Entrada mañana'
            WHEN a.registro = 'salida_manana' THEN 'Salida mañana'
            WHEN a.registro = 'entrada_tarde' THEN 'Entrada Tarde'
            WHEN a.registro = 'salida_tarde' THEN 'Salida tarde'
            ELSE a.registro 
            END AS registro 
            FROM asistencias a 
            JOIN empleados b ON b._id = a.id_empleado 
            WHERE DATE(a.moment) BETWEEN '" . $args['fecha_inicio'] . "' AND '" . $args['fecha_fin'] . "'
            ORDER BY b.nombre ASC, a.moment ASC,
            FIELD(a.registro, 'entrada_manana', 'salida_manana', 'entrada_tarde', 'salida_tarde');
            ";
        $object['detallado'] = $localConnection->goQuery($sql);

        $localConnection->disconnect();

        $response->getBody()->write(json_encode($object));

        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus(200);
    });
    /* FIN ASISTENCIAS */
};
