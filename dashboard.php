<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

// Get check statistics
try {
    $stmt = $conn->prepare("SELECT status, COUNT(*) as count FROM checks GROUP BY status");
    $stmt->execute();
    $checkStats = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM checks WHERE status = 'protestado'");
    $stmt->execute();
    $protestedChecks = $stmt->fetchColumn();
} catch(PDOException $e) {
    $error = "Error al cargar estadísticas: " . $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <h2 class="my-4">Panel de Control</h2>
    
    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <div class="row">
        <!-- Quick Stats Cards -->
        <div class="col-md-4 mb-4">
            <div class="card text-white bg-primary">
                <div class="card-body">
                    <h5 class="card-title">Cheques Registrados</h5>
                    <?php
                    $totalChecks = 0;
                    foreach ($checkStats as $stat) {
                        $totalChecks += $stat['count'];
                    }
                    ?>
                    <p class="display-4"><?php echo $totalChecks; ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card text-white bg-success">
                <div class="card-body">
                    <h5 class="card-title">Cheques Depositados</h5>
                    <?php
                    $deposited = 0;
                    foreach ($checkStats as $stat) {
                        if ($stat['status'] === 'depositado') {
                            $deposited = $stat['count'];
                            break;
                        }
                    }
                    ?>
                    <p class="display-4"><?php echo $deposited; ?></p>
                </div>
            </div>
        </div>

        <div class="col-md-4 mb-4">
            <div class="card text-white bg-danger">
                <div class="card-body">
                    <h5 class="card-title">Cheques Protestados</h5>
                    <p class="display-4"><?php echo $protestedChecks; ?></p>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Checks Table -->
    <div class="card mb-4">
        <div class="card-header">
            <h5 class="mb-0">Últimos Cheques Registrados</h5>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Beneficiario</th>
                            <th>Valor</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        try {
                            $stmt = $conn->prepare("
                                SELECT check_number, beneficiary, amount, status, issue_date 
                                FROM checks 
                                ORDER BY issue_date DESC 
                                LIMIT 5
                            ");
                            $stmt->execute();
                            $recentChecks = $stmt->fetchAll(PDO::FETCH_ASSOC);
                            
                            foreach ($recentChecks as $check): 
                                $statusClass = [
                                    'ingresado' => 'bg-primary',
                                    'depositado' => 'bg-success',
                                    'protestado' => 'bg-danger'
                                ][$check['status']] ?? 'bg-secondary';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($check['check_number']); ?></td>
                            <td><?php echo htmlspecialchars($check['beneficiario']); ?></td>
                            <td>$<?php echo number_format($check['amount'], 2); ?></td>
                            <td>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($check['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($check['issue_date'])); ?></td>
                        </tr>
                        <?php endforeach; ?>
                        <?php } catch(PDOException $e) { ?>
                        <tr>
                            <td colspan="5" class="text-center text-danger">
                                Error al cargar cheques: <?php echo $e->getMessage(); ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <div class="text-end">
                <a href="checks.php" class="btn btn-primary">Ver todos los cheques</a>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>