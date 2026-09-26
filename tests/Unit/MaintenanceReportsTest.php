<?php
use PHPUnit\Framework\TestCase;

require_once __DIR__ . '/../../lib/MaintenanceReports.php';

final class MaintenanceReportsTest extends TestCase
{
    private static PDO $pdo;
    private static string $roomId;
    private static int $adminId;
    private static int $usuarioId;

    public static function setUpBeforeClass(): void
    {
        $host = getenv('TEST_DB_HOST') ?: '127.0.0.1';
        $name = getenv('TEST_DB_NAME') ?: 'habitara_ci';
        $user = getenv('TEST_DB_USER') ?: 'habitara_ci';
        $pass = getenv('TEST_DB_PASS') ?: 'ci_pass_123';

        self::$pdo = new PDO("mysql:host={$host};dbname={$name};charset=utf8mb4", $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        // Toma el primer cuarto que exista (el schema de ejemplo siempre trae al menos uno).
        self::$roomId = self::$pdo->query("SELECT id FROM rooms LIMIT 1")->fetchColumn();

        // Usuarios de prueba, uno de cada rol.
        self::$pdo->exec("DELETE FROM staff_users WHERE username LIKE 'phpunit_%'");
        self::$pdo->exec("INSERT INTO staff_users (username, password_hash, full_name, role) VALUES
            ('phpunit_admin', 'x', 'PHPUnit Admin', 'admin'),
            ('phpunit_usuario', 'x', 'PHPUnit Usuario', 'usuario')");
        self::$adminId = (int)self::$pdo->query("SELECT id FROM staff_users WHERE username='phpunit_admin'")->fetchColumn();
        self::$usuarioId = (int)self::$pdo->query("SELECT id FROM staff_users WHERE username='phpunit_usuario'")->fetchColumn();
    }

    protected function setUp(): void
    {
        // Limpia los reportes de prueba antes de cada test, para que no se acumulen entre pruebas.
        self::$pdo->exec("DELETE FROM maintenance_reports WHERE reported_by IN (" . self::$adminId . "," . self::$usuarioId . ")");
    }

    public static function tearDownAfterClass(): void
    {
        self::$pdo->exec("DELETE FROM maintenance_reports WHERE reported_by IN (" . self::$adminId . "," . self::$usuarioId . ")");
        self::$pdo->exec("DELETE FROM staff_users WHERE username LIKE 'phpunit_%'");
    }

    // --- Permisos por rol ---

    public function testAdminPuedeTodo(): void
    {
        $this->assertTrue(mr_can('admin', 'create'));
        $this->assertTrue(mr_can('admin', 'list'));
        $this->assertTrue(mr_can('admin', 'update_status'));
        $this->assertTrue(mr_can('admin', 'delete'));
    }

    public function testUsuarioSoloPuedeCrearYVer(): void
    {
        $this->assertTrue(mr_can('usuario', 'create'));
        $this->assertTrue(mr_can('usuario', 'list'));
        $this->assertFalse(mr_can('usuario', 'update_status'));
        $this->assertFalse(mr_can('usuario', 'delete'));
    }

    public function testRolDesconocidoNoTieneNingunPermiso(): void
    {
        $this->assertFalse(mr_can('rol-que-no-existe', 'list'));
    }

    // --- Crear reportes ---

    public function testCrearReporteValido(): void
    {
        $id = mr_create(self::$pdo, [
            'room_id' => self::$roomId,
            'title' => 'Foco fundido',
            'description' => 'El foco del baño no enciende',
            'priority' => 'baja',
            'reported_by' => self::$usuarioId,
        ]);

        $this->assertGreaterThan(0, $id);
    }

    public function testCrearReporteSinTituloLanzaExcepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mr_create(self::$pdo, ['room_id' => self::$roomId, 'reported_by' => self::$usuarioId]);
    }

    public function testCrearReporteConPrioridadInvalidaLanzaExcepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mr_create(self::$pdo, [
            'room_id' => self::$roomId, 'title' => 'X', 'priority' => 'urgentisimo', 'reported_by' => self::$usuarioId,
        ]);
    }

    public function testCrearReporteSinReportedByLanzaExcepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mr_create(self::$pdo, ['room_id' => self::$roomId, 'title' => 'X']);
    }

    // --- Listar reportes ---

    public function testListarReportesDevuelveLosCreados(): void
    {
        mr_create(self::$pdo, ['room_id' => self::$roomId, 'title' => 'Reporte A', 'reported_by' => self::$usuarioId]);
        mr_create(self::$pdo, ['room_id' => self::$roomId, 'title' => 'Reporte B', 'reported_by' => self::$adminId]);

        $reports = mr_list(self::$pdo);
        $titles = array_column($reports, 'title');

        $this->assertContains('Reporte A', $titles);
        $this->assertContains('Reporte B', $titles);
    }

    public function testListarFiltradoPorEstado(): void
    {
        $id = mr_create(self::$pdo, ['room_id' => self::$roomId, 'title' => 'Reporte C', 'reported_by' => self::$usuarioId]);
        mr_update_status(self::$pdo, $id, 'cerrado', self::$adminId);

        $abiertos = mr_list(self::$pdo, 'abierto');
        $cerrados = mr_list(self::$pdo, 'cerrado');

        $this->assertNotContains('Reporte C', array_column($abiertos, 'title'));
        $this->assertContains('Reporte C', array_column($cerrados, 'title'));
    }

    public function testListarConEstadoInvalidoLanzaExcepcion(): void
    {
        $this->expectException(InvalidArgumentException::class);
        mr_list(self::$pdo, 'estado-inventado');
    }

    // --- Cambiar estado ---

    public function testCerrarReporteRegistraQuienLoCerro(): void
    {
        $id = mr_create(self::$pdo, ['room_id' => self::$roomId, 'title' => 'Reporte D', 'reported_by' => self::$usuarioId]);
        $ok = mr_update_status(self::$pdo, $id, 'cerrado', self::$adminId);

        $this->assertTrue($ok);

        $row = self::$pdo->query("SELECT status, closed_by, closed_at FROM maintenance_reports WHERE id = {$id}")->fetch();
        $this->assertSame('cerrado', $row['status']);
        $this->assertSame(self::$adminId, (int)$row['closed_by']);
        $this->assertNotNull($row['closed_at']);
    }

    public function testReabrirUnReporteLimpiaClosedBy(): void
    {
        $id = mr_create(self::$pdo, ['room_id' => self::$roomId, 'title' => 'Reporte E', 'reported_by' => self::$usuarioId]);
        mr_update_status(self::$pdo, $id, 'cerrado', self::$adminId);
        mr_update_status(self::$pdo, $id, 'en_proceso', self::$adminId);

        $row = self::$pdo->query("SELECT status, closed_by FROM maintenance_reports WHERE id = {$id}")->fetch();
        $this->assertSame('en_proceso', $row['status']);
        $this->assertNull($row['closed_by']);
    }

    public function testCambiarEstadoInvalidoLanzaExcepcion(): void
    {
        $id = mr_create(self::$pdo, ['room_id' => self::$roomId, 'title' => 'Reporte F', 'reported_by' => self::$usuarioId]);
        $this->expectException(InvalidArgumentException::class);
        mr_update_status(self::$pdo, $id, 'estado-invalido', self::$adminId);
    }

    public function testCambiarEstadoDeReporteInexistenteDevuelveFalse(): void
    {
        $ok = mr_update_status(self::$pdo, 999999, 'cerrado', self::$adminId);
        $this->assertFalse($ok);
    }

    // --- Eliminar ---

    public function testEliminarReporteExistente(): void
    {
        $id = mr_create(self::$pdo, ['room_id' => self::$roomId, 'title' => 'Reporte G', 'reported_by' => self::$usuarioId]);
        $ok = mr_delete(self::$pdo, $id);

        $this->assertTrue($ok);
        $count = self::$pdo->query("SELECT COUNT(*) FROM maintenance_reports WHERE id = {$id}")->fetchColumn();
        $this->assertSame('0', (string)$count);
    }

    public function testEliminarReporteInexistenteDevuelveFalse(): void
    {
        $ok = mr_delete(self::$pdo, 999999);
        $this->assertFalse($ok);
    }
}
