<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

// Only admin can access this page
if (!isAdmin()) {
    header("Location: dashboard.php");
    exit();
}

$userId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    // Get user details
    $stmt = $conn->prepare("SELECT id, username, email, role, created_at FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Usuario no encontrado");
    }

    // Get user activity stats
    $stmt = $conn->prepare("
        SELECT 
            (SELECT COUNT(*) FROM checks WHERE user_id = :id) as checks_count,
            (SELECT COUNT(*) FROM invoices WHERE user_id = :id) as invoices_count,
            (SELECT COUNT(*) FROM clients WHERE user_id = :id) as clients_count
    ");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
} catch(Exception $e) {
    $error = $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Detalles del Usuario</h2>
        <div>
            <a href="edit-user.php?id=<?php echo $userId; ?>" class="btn btn-warning me-2">
                <i class="fas fa-edit"></i> Editar
            </a>
            <a href="users.php" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Volver
            </a>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php else: ?>
    
    <div class="row">
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Información Básica</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Usuario:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($user['username']); ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Email:</label>
                        <div class="col-sm-8">
                            <p class="form-control-plaintext"><?php echo htmlspecialchars($user['email']); ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-4 col-form-label">Rol:</label>
                        <div class="col-sm-8">
                            <span class="badge <?php echo $user['role'] === 'admin' ? 'bg-primary' : 'bg-secondary'; ?>">
                                <?php echo ucfirst($user['role']); ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Estadísticas</h5>
                </div>
                <div class="card-body">
                    <div class="mb-3 row">
                        <label class="col-sm-6 col-form-label">Fecha Registro:</label>
                        <div class="col-sm-6">
                            <p class="form-control-plaintext">
                                <?php echo date('d/m/Y', strtotime($user['created_at'])); ?>
                            </p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-6 col-form-label">Cheques Registrados:</label>
                        <div class="col-sm-6">
                            <p class="form-control-plaintext"><?php echo $stats['checks_count']; ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-6 col-form-label">Facturas Creadas:</label>
                        <div class="col-sm-6">
                            <p class="form-control-plaintext"><?php echo $stats['invoices_count']; ?></p>
                        </div>
                    </div>
                    <div class="mb-3 row">
                        <label class="col-sm-6 col-form-label">Clientes Registrados:</label>
                        <div class="col-sm-6">
                            <p class="form-control-plaintext"><?php echo $stats['clients_count']; ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include 'includes/footer.php'; ?>