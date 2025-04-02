<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

// Pagination variables
$limit = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

// Search filter
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

try {
    // Build query
    $query = "SELECT * FROM clients";
    $countQuery = "SELECT COUNT(*) FROM clients";
    $params = [];

    if (!empty($search)) {
        $query .= " WHERE name LIKE :search OR contact LIKE :search";
        $countQuery .= " WHERE name LIKE :search OR contact LIKE :search";
        $params[':search'] = "%$search%";
    }

    $query .= " ORDER BY name ASC LIMIT :limit OFFSET :offset";

    // Get clients
    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get total count
    $stmt = $conn->prepare($countQuery);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $totalClients = $stmt->fetchColumn();
    $totalPages = ceil($totalClients / $limit);
} catch(PDOException $e) {
    $error = "Error al cargar clientes: " . $e->getMessage();
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Gestión de Clientes</h2>
        <a href="add-client.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Nuevo Cliente
        </a>
    </div>

    <!-- Search Form -->
    <div class="card mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-8">
                    <input type="text" class="form-control" name="search" 
                           placeholder="Buscar por nombre o contacto" value="<?php echo htmlspecialchars($search); ?>">
                </div>
                <div class="col-md-4">
                    <button type="submit" class="btn btn-primary w-100">
                        <i class="fas fa-search"></i> Buscar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (isset($error)): ?>
    <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Clients Table -->
    <div class="card">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Nombre</th>
                            <th>Contacto</th>
                            <th>Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($clients as $client): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($client['name']); ?></td>
                            <td><?php echo htmlspecialchars($client['contact']); ?></td>
                            <td>
                                <a href="view-client.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-info" title="Ver">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="edit-client.php?id=<?php echo $client['id']; ?>" class="btn btn-sm btn-warning" title="Editar">
                                    <i class="fas fa-edit"></i>
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php if (empty($clients)): ?>
                        <tr>
                            <td colspan="3" class="text-center">No se encontraron clientes</td>
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
                        <a class="page-link" href="?page=<?php echo $page-1; ?>&search=<?php echo urlencode($search); ?>">
                            Anterior
                        </a>
                    </li>
                    
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                    <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                    <?php endfor; ?>
                    
                    <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                        <a class="page-link" href="?page=<?php echo $page+1; ?>&search=<?php echo urlencode($search); ?>">
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