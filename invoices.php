<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

// Pagination variables
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Filter variables
$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Build query
    $query = "SELECT i.*, c.name as client_name FROM invoices i
              LEFT JOIN clients c ON i.client_id = c.id
              WHERE 1=1";
    $params = [];

    if ($clientId > 0) {
        $query .= " AND i.client_id = :client_id";
        $params[':client_id'] = $clientId;
    }

    if (!empty($status)) {
        $query .= " AND i.status = :status";
        $params[':status'] = $status;
    }

    if (!empty($search)) {
        $query .= " AND (i.id LIKE :search OR c.name LIKE :search)";
        $params[':search'] = "%$search%";
    }

    $query .= " ORDER BY i.due_date DESC LIMIT :limit OFFSET :offset";

    // Get invoices
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $invoices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $countQuery = str_replace("ORDER BY i.due_date DESC LIMIT :limit OFFSET :offset", "", $query);
    $stmt = $conn->prepare("SELECT COUNT(*) FROM ($countQuery) as total");
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalInvoices = $stmt->fetchColumn();
    $totalPages = ceil($totalInvoices / $limit);

    // Get clients for filter dropdown
    $stmt = $conn->query("SELECT id, name FROM clients ORDER BY name");
    $allClients = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error = "Error al cargar facturas: " . $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestión de Facturas</h2>
        <a href="add-invoice.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Nueva Factura
        </a>
    </div>

    <!-- Filter Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label for="client_id" class="form-label">Cliente</label>
                    <select id="client_id" name="client_id" class="form-select">
                        <option value="">Todos</option>
                        <?php foreach ($allClients as $client): ?>
                        <option value="<?php echo $client['id']; ?>"
                            <?php echo $clientId == $client['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($client['name']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label for="status" class="form-label">Estado</label>
                    <select id="status" name="status" class="form-select">
                        <option value="">Todos</option>
                        <option value="pendiente" <?php echo $status === 'pendiente' ? 'selected' : ''; ?>>Pendiente</option>
                        <option value="pagada" <?php echo $status === 'pagada' ? 'selected' : ''; ?>>Pagada</option>
                        <option value="cancelada" <?php echo $status === 'cancelada' ? 'selected' : ''; ?>>Cancelada</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="search" class="form-label">Buscar</label>
                    <input type="text" class="form-control" id="search" name="search" 
                           placeholder="Número o cliente" value="<?php echo htmlspecialchars($search); ?>">
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

    <!-- Invoices Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Cliente</th>
                            <th>Monto</th>
                            <th>Vencimiento</th>
                            <th>Estado</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $invoice): 
                            $statusClass = [
                                'pendiente' => 'bg-warning',
                                'pagada' => 'bg-success',
                                'cancelada' => 'bg-danger'
                            ][$invoice['status']] ?? 'bg-secondary';
                        ?>
                        <tr>
                            <td><?php echo $invoice['id']; ?></td>
                            <td><?php echo htmlspecialchars($invoice['client_name']); ?></td>
                            <td>$<?php echo number_format($invoice['amount'], 2); ?></td>
                            <td><?php echo date('d/m/Y', strtotime($invoice['due_date'])); ?></td>
                            <td>
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo ucfirst($invoice['status']); ?>
                                </span>
                            </td>
                            <td>
                                <a href="view-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-info" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-invoice.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($invoices)): ?>
                        <tr>
                            <td colspan="6" class="text-center">No se encontraron facturas</td>
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
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&client_id=<?php echo $clientId; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                            Anterior
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&client_id=<?php echo $clientId; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&client_id=<?php echo $clientId; ?>&status=<?php echo $status; ?>&search=<?php echo urlencode($search); ?>">
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