use gestionturnos;

/***************************************************************
-- TRIGGER: para administradores          --
***************************************************************/
-- DELIMITER //

CREATE TRIGGER after_insert_usuario
AFTER INSERT ON usuarios
FOR EACH ROW
BEGIN
  -- Solo inserta si el usuario tiene rol de Administrador (id_rol = 3)
  IF NEW.id_rol = 3 THEN
    INSERT INTO administradores (id_usuario)
    VALUES (NEW.id_usuario);
  END IF;
END -- //

-- DELIMITER ;


/***************************************************************
-- PROCEDIMIENTO: para medicos            --
***************************************************************/
-- DELIMITER //

CREATE PROCEDURE insertar_usuario_medico(
    IN p_nombre VARCHAR(100),
    IN p_apellido VARCHAR(100),
    IN p_email VARCHAR(150),
    IN p_password_hash VARCHAR(255),
    IN p_id_rol INT,
    IN p_activo TINYINT(1),
    IN p_genero VARCHAR(100),
    IN p_img_dni LONGTEXT,
    IN p_matricula VARCHAR(100),
    IN p_telefono VARCHAR(50)
)
BEGIN
    DECLARE nuevo_id INT;

    -- Insertar en usuarios
    INSERT INTO usuarios (nombre, apellido, email, password_hash, id_rol, activo, genero, img_dni)
    VALUES (p_nombre, p_apellido, p_email, p_password_hash, p_id_rol, p_activo, p_genero, p_img_dni);

    -- Obtener el id generado
    SET nuevo_id = LAST_INSERT_ID();

    -- Si es médico, insertar en medicos
    IF p_id_rol = 2 THEN
        INSERT INTO medicos (id_usuario, matricula, telefono)
        VALUES (nuevo_id, p_matricula, p_telefono);
    END IF;

END -- //

-- DELIMITER ;


/***************************************************************
-- PROCEDIMIENTO: para tecnicos           --
***************************************************************/
-- DELIMITER //

CREATE PROCEDURE insertar_usuario_tecnico (
    IN p_nombre VARCHAR(100),
    IN p_apellido VARCHAR(100),
    IN p_email VARCHAR(150),
    IN p_password_hash VARCHAR(255),
    IN p_activo TINYINT(1),
    IN p_genero VARCHAR(100),
    IN p_img_dni LONGTEXT,
    IN p_recurso_nombre VARCHAR(150)
)
BEGIN
    DECLARE v_id_usuario INT;
    DECLARE v_id_recurso INT;

    -- 1️⃣ Crear el recurso primero con id_sede = 1
    INSERT INTO recursos (nombre, tipo, id_sede)
    VALUES (p_recurso_nombre, 'tecnico', 1);  -- 👈 Agregado id_sede = 1
    
    SET v_id_recurso = LAST_INSERT_ID();

    -- 2️⃣ Insertar usuario con rol Técnico (id_rol = 4)
    INSERT INTO usuarios (nombre, apellido, email, password_hash, id_rol, activo, genero, img_dni)
    VALUES (p_nombre, p_apellido, p_email, p_password_hash, 4, p_activo, p_genero, p_img_dni);

    SET v_id_usuario = LAST_INSERT_ID();

    -- 3️⃣ Insertar en la tabla tecnicos
    INSERT INTO tecnicos (id_usuario, id_rol, id_recurso)
    VALUES (v_id_usuario, 4, v_id_recurso);
    
    -- 4️⃣ Devolver los IDs creados
    SELECT v_id_usuario AS id_usuario, LAST_INSERT_ID() AS id_tecnico, v_id_recurso AS id_recurso;
END -- //

-- DELIMITER ;



/***************************************************************
-- PROCEDIMIENTO: para pacientes          --
***************************************************************/
DELIMITER $$
CREATE PROCEDURE insertar_usuario_paciente(
    IN p_nombre VARCHAR(100),
    IN p_apellido VARCHAR(100),
    IN p_email VARCHAR(150),
    IN p_password_hash VARCHAR(255),
    IN p_activo TINYINT(1),
    IN p_genero VARCHAR(50),
    IN p_tipo_documento VARCHAR(50),
    IN p_nro_documento VARCHAR(50),
    IN p_fecha_nacimiento DATE,
    IN p_direccion VARCHAR(255),
    IN p_telefono VARCHAR(50),
    IN p_estado_civil VARCHAR(50),
    IN p_token_qr VARCHAR(255),
    IN p_img_dni LONGTEXT,
    OUT p_id_paciente INT
)
BEGIN
    DECLARE v_id_usuario INT;

    -- Insertar usuario con rol Paciente (id_rol = 1)
    INSERT INTO usuarios (nombre, apellido, email, password_hash, id_rol, activo, genero, img_dni)
    VALUES (p_nombre, p_apellido, p_email, p_password_hash, 1, p_activo, p_genero, p_img_dni);

    SET v_id_usuario = LAST_INSERT_ID();

    -- Insertar en pacientes
    INSERT INTO pacientes (
        id_usuario, tipo_documento, nro_documento, fecha_nacimiento,
        direccion, telefono, email, estado_civil, token_qr, id_titular
    ) VALUES (
        v_id_usuario, p_tipo_documento, p_nro_documento, p_fecha_nacimiento,
        p_direccion, p_telefono, p_email, p_estado_civil, p_token_qr, NULL
    );
    
    -- Retornar el id del paciente creado
    SET p_id_paciente = LAST_INSERT_ID();
END$$
DELIMITER ;



/***************************************************************
-- PROCEDIMIENTO: para administrativo          --
***************************************************************/
DELIMITER $$

CREATE PROCEDURE sp_crear_administrativo (
    IN p_nombre VARCHAR(100),
    IN p_apellido VARCHAR(100),
    IN p_email VARCHAR(150),
    IN p_password_hash VARCHAR(255),
    IN p_genero VARCHAR(100),
    IN p_dni VARCHAR(20),
    IN p_telefono VARCHAR(30),
    IN p_direccion VARCHAR(255)
)
BEGIN
    DECLARE v_id_usuario INT;

    -- Verificar si ya existe el email
    IF EXISTS (SELECT 1 FROM usuarios WHERE email = p_email) THEN
        SIGNAL SQLSTATE '45000'
            SET MESSAGE_TEXT = 'El correo electrónico ya está registrado';
    END IF;

    -- Insertar en usuarios con rol=5 (Administrativo)
    INSERT INTO usuarios (nombre, apellido, email, password_hash, id_rol, genero, activo)
    VALUES (p_nombre, p_apellido, p_email, p_password_hash, 5, p_genero, 1);

    SET v_id_usuario = LAST_INSERT_ID();

    -- Insertar en administrativos
    INSERT INTO administrativos (id_usuario, dni, telefono, direccion, activo)
    VALUES (v_id_usuario, p_dni, p_telefono, p_direccion, 1);
END$$

DELIMITER ;


/***************************************************************
-- PROCEDIMIENTO: para afiliado menor          --
***************************************************************/
DELIMITER $$
CREATE PROCEDURE insertar_paciente_afiliado_menor(
    IN p_id_titular INT,
    IN p_tipo_documento VARCHAR(50),
    IN p_numero_documento VARCHAR(50),
    IN p_nombre VARCHAR(100),
    IN p_apellido VARCHAR(100),
    IN p_fecha_nacimiento DATE,
    IN p_token_qr VARCHAR(255)
)
BEGIN
    -- Insertar menor en pacientes (sin id_usuario ya que no puede acceder al sistema)
    INSERT INTO pacientes (
        id_usuario,
        id_titular,
        tipo_documento,
        nro_documento,
        fecha_nacimiento,
        direccion,
        telefono,
        email,
        estado_civil,
        token_qr
    ) VALUES (
        NULL,  -- Los menores no tienen usuario
        p_id_titular,
        p_tipo_documento,
        p_numero_documento,
        p_fecha_nacimiento,
        NULL,  -- Heredan datos del titular
        NULL,
        NULL,
        NULL,
        p_token_qr
    );
    
    -- Actualizar tabla afiliados con datos si no existen
    UPDATE afiliados
    SET nombre = COALESCE(nombre, p_nombre),
        apellido = COALESCE(apellido, p_apellido),
        fecha_nacimiento = COALESCE(fecha_nacimiento, p_fecha_nacimiento)
    WHERE numero_documento = p_numero_documento;
END$$
DELIMITER ;



