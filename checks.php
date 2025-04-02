<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

// Pagination variables
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Get filter parameters
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Build query
$query = "SELECT c.id, c.check_number, c.beneficiary, c.amount, c.status, c.issue_date, 
                 cl.name as client_name, i.id as invoice_id
          FROM checks c
          LEFT JOIN clients cl ON c.client_id = cl.id
          LEFT JOIN invoices i ON c.invoice_id = i.id
          WHERE 1=1";
$params = [];

if (!empty($status)) {
    $query .= " AND c.status = :status";
    $params[':status'] = $status;
}

if (!empty($search)) {
    $query .= " AND (c.check_number LIKE :search OR c.beneficiary LIKE :search OR cl.name LIKE :search)";
    $params[':search'] = "%$search%";
}

$query .= " ORDER BY c.issue_date DESC LIMIT :limit OFFSET :offset";

try {
    // Get checks
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $checks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count for pagination
    $countQuery = str_replace("LIMIT :limit OFFSET :offset", "", $query);
    $stmt = $conn->prepare("SELECT COUNT(*) FROM ($countQuery) as total");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalChecks = $stmt->fetchColumn();
    $totalPages = ceil($totalChecks / $limit);
} catch(PDOException $e) {
    $error = "Error al cargar cheques: " . $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestión de Cheques</h2>
        <a href="add-check.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Nuevo Cheque
        </a>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label for="status" class="form-label">Estado</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="ingresado" <?php echo $status === 'ingresado' ? 'selected' : ''; ?>>Ingresado</option>
                        <option value="depositado" <?php echo $status === 'depositado' ? 'selected' : ''; ?>>Depositado</option>
                        <option value="protestado" <?php echo $status === 'protestado' ? 'selected' : ''; ?>>Protestado</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Número, beneficiario o cliente" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-filter"></i> Filtrar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Checks Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Número</th>
                            <th>Beneficiario</th>
                            <th>Cliente</th>
                            <th>Factura</th>
                            <th>Valor</th>
                            <th>Estado</th>
                            <th>Fecha</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($checks as $check): 
                            $statusClass = [
                                'ingresado' => 'bg-primary',
                                'depositado' => 'bg-success',
                                'protestado' => 'bg-danger'
                            ][$check['status']] ?? 'bg-secondary';
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($check['check_number']); ?></td>
                            <td><?php echo htmlspecialchars($check['beneficiary']); ?></td>
                            <td><?php echo htmlspecialchars($check['client_name']); ?></td>
                            <td><?php echo $check['invoice_id'] ? '#' . $check['invoice_id'] : 'N/A'; ?></td>
                            <td>$<?php echo number_format($check['amount'], 2); ?></td>
                            <td>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($check['status']); ?>
                                </span>
                            </td>
                            <td><?php echo date('d/m/Y', strtotime($check['issue_date'])); ?></td>
                            <td>
                                <a href="view-check.php?id=<?php echo $check['id']; ?>" class="btn btn-sm btn-info" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-check.php?id=<?php echo $check['id']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($checks)): ?>
                        <tr>
                            <td colspan="8" class="text-center">No se encontraron cheques</td>
                        </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Pagination -->
            <?php if ($totalPages > 1): ?>
            <nav aria-label="Page navigation" class="mt-4">
                <ul class="pagination justify-content-center">
                    <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                            Anterior
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                            Siguiente
                        </a>
                    </li>
                </ul>
            </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>