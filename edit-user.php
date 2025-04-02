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
$error = '';
$success = '';

// Get user details
try {
    $stmt = $conn->prepare("SELECT id, username, email, role FROM users WHERE id = :id");
    $stmt->bindParam(':id', $userId);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        throw new Exception("Usuario no encontrado");
    }
} catch(Exception $e) {
    $error = $e->getMessage();
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $changePassword = isset($_POST['change_password']) && $_POST['change_password'] === '1';
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Validate inputs
    $errors = [];
    if (empty($username)) $errors[] = "El usuario es requerido";
    if (empty($email)) $errors[] = "El email es requerido";
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = "Email inválido";
    if (!in_array($role, ['admin', 'user'])) $errors[] = "Rol inválido";
    
    if ($changePassword) {
        if (strlen($password) < 8) $errors[] = "La contraseña debe tener al menos 8 caracteres";
        if ($password !== $confirmPassword) $errors[] = "Las contraseñas no coinciden";
    }

    if (empty($errors)) {
        try {
            // Check if username or email exists for another user
            $stmt = $conn->prepare("SELECT id FROM users WHERE (username = :username OR email = :email) AND id != :id");
            $stmt->bindParam(':username', $username);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':id', $userId);
            $stmt->execute();
            
            if ($stmt->rowCount() === 0) {
                if ($changePassword) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $stmt = $conn->prepare("
                        UPDATE users SET
                        username = :username,
                        email = :email,
                        password = :password,
                        role = :role
                        WHERE id = :id
                    ");
                    $stmt->bindParam(':password', $hashedPassword);
                } else {
                    $stmt = $conn->prepare("
                        UPDATE users SET
                        username = :username,
                        email = :email,
                        role = :role
                        WHERE id = :id
                    ");
                }
                
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':role', $role);
                $stmt->bindParam(':id', $userId);
                
                if ($stmt->execute()) {
                    $success = "Usuario actualizado exitosamente";
                    // Refresh user data
                    $stmt = $conn->prepare("SELECT * FROM users WHERE id = :id");
                    $stmt->bindParam(':id', $userId);
                    $stmt->execute();
                    $user = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            } else {
                $errors[] = "El usuario o email ya existe";
            }
        } catch(PDOException $e) {
            $errors[] = "Error al actualizar usuario: " . $e->getMessage();
        }
    }
    
    if (!empty($errors)) {
        $error = implode("<br>", $errors);
    }
}
?>

<?php include 'includes/header.php'; ?>

<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Editar Usuario</h4>
                </div>
                <div class="card-body">
                    <?php if ($error): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <?php if ($success): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                    <?php endif; ?>

                    <?php if (isset($user)): ?>
                    <form method="POST">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="username" class="form-label">Usuario*</label>
                                <input type="text" class="form-control" id="username" name="username" 
                                       value="<?php echo htmlspecialchars($user['username']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="email" class="form-label">Email*</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" required>
                            </div>
                            
                            <div class="col-md-6">
                                <label for="role" class="form-label">Rol*</label>
                                <select class="form-select" id="role" name="role" required>
                                    <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>Usuario</option>
                                    <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Administrador</option>
                                </select>
                            </div>
                            
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="change_password" name="change_password" value="1">
                                    <label class="form-check-label" for="change_password">
                                        Cambiar contraseña
                                    </label>
                                </div>
                            </div>
                            
                            <div class="col-md-6 password-fields" style="display: none;">
                                <label for="password" class="form-label">Nueva Contraseña*</label>
                                <input type="password" class="form-control" id="password" name="password">
                            </div>
                            
                            <div class="col-md-6 password-fields" style="display: none;">
                                <label for="confirm_password" class="form-label">Confirmar Contraseña*</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password">
                            </div>
                            
                            <div class="col-12 mt-4">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> Guardar Cambios
                                </button>
                                <a href="view-user.php?id=<?php echo $userId; ?>" class="btn btn-secondary ms-2">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </a>
                            </div>
                        </div>
                    </form>

                    <script>
                    // Show/hide password fields based on checkbox
                    document.getElementById('change_password').addEventListener('change', function() {
                        const passwordFields = document.querySelectorAll('.password-fields');
                        const required = this.checked;
                        
                        passwordFields.forEach(field => {
                            field.style.display = this.checked ? 'block' : 'none';
                            const inputs = field.querySelectorAll('input');
                            inputs.forEach(input => input.required = required);
                        });
                    });
                    </script>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>