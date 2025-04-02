<?php
require_once 'includes/session.php';
require_once 'includes/db.php';
redirectIfNotLoggedIn();

$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$error = '';
$success = '';

// Get client details
try {
    $stmt = $conn->prepare("SELECT * FROM clients WHERE id = :id");
    $stmt->bindParam(':id', $clientId);
    $stmt->execute();
    $client = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$client) {
        throw new Exception("Cliente no encontrado");
    }
} catch(Exception $e) {
    $error = $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $contact = trim($_POST['contact']);
    $email = trim($_POST['email']);
    $address = trim($_POST['address']);

    // Validate inputs
    if (empty($name)) {
        $error = "El nombre del cliente es requerido";
    } else {
        try {
            $stmt = $conn->prepare("
                UPDATE clients SET
                name = :name,
                contact = :contact,
                email = :email,
                address = :address
                WHERE id = :id
            ");
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':contact', $contact);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':id', $clientId);
            
            if ($stmt->execute()) {
                $success = "Cliente actualizado exitosamente";
                // Refresh client data
                $stmt = $conn->prepare("SELECT * FROM clients WHERE id = :id");
                $stmt->bindParam(':id', $clientId);
                $stmt->execute();
                $client = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        } catch(PDOException $e) {
            $error = "Error al actualizar cliente: " . $e->getMessage();
        }
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Editar Cliente</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <?php if (isset($client)): ?>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-12">
                                <label for="name" class="form-label">Nombre*</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?php echo htmlspecialchars($client['name']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="contact" class="form-label">Contacto</label>
                                <input type="text" class="form-control" id="contact" name="contact" 
                                       value="<?php echo htmlspecialchars($client['contact']); ?>">
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($client['email']); ?>">
                            </div>
                            
                            <div class="col-12">
                                <label for="address" class="form-label">Dirección</label>
                                <textarea class="form-control" id="address" name="address" rows="2"><?php 
                                    echo htmlspecialchars($client['address']); 
                                ?></textarea>
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Guardar Cambios
                                </button>
                                <a href="view-client.php?id=<?php echo $clientId; ?>" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </a>
                            </div>
                        </div>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>