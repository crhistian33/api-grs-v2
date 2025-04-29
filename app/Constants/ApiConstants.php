<?php

namespace App\Constants;

class ApiConstants
{
    public const CREATE_SUCCESS_TITLE = 'Creación exitosa';
    public const CREATE_SUCCESS_MESSAGE = 'El recurso ha sido creado exitosamente.';

    public const UPDATE_SUCCESS_TITLE = 'Actualización exitosa';
    public const UPDATE_SUCCESS_MESSAGE = 'El recurso ha sido actualizado exitosamente.';

    public const RENEW_SUCCESS_TITLE = 'Renovación exitosa';
    public const RENEW_SUCCESS_MESSAGE = 'El contrato ha sido renovado exitosamente.';

    public const DELETE_SUCCESS_TITLE = 'Eliminación exitosa';
    public const DELETE_SUCCESS_MESSAGE = 'El recurso ha sido eliminado exitosamente.';

    public const DELETEALL_SUCCESS_MESSAGE = 'Los recursos han sido eliminados exitosamente.';
    public const DELETEALL_INCOMPLETE_SUCCESS_MESSAGE = 'Los recursos han sido eliminados exitosamente, no encontrando algunos de ellos.';
    public const DELETEALL_RELATIONS_SUCCESS_MESSAGE = 'Los recursos han sido eliminados exitosamente, pero no se eliminaron los registros relacionados.';
    public const DELETEALL_NOTFOUND_SUCCESS_MESSAGE = 'No se encontraron recursos para eliminar.';

    public const DELETE_FORCE_SUCCESS_TITLE = 'Eliminación exitosa';
    public const DELETE_FORCE_SUCCESS_MESSAGE = 'El recurso ha sido eliminado permanentemente con éxito.';
    public const DELETEALL_FORCE_SUCCESS_MESSAGE = 'Los recursos han sido eliminados permanentemente con éxito.';
    public const DELETEALL_FORCE_INCOMPLETE_SUCCESS_MESSAGE = 'Los recursos han sido eliminados permanentemente con éxito, no encontrando algunos de ellos.';
    public const DELETEALL_FORCE_NOTFOUND_SUCCESS_MESSAGE = 'No se encontraron recursos para eliminar permanentemente.';

    public const RESTORE_SUCCESS_TITLE = 'Restauración exitosa';
    public const RESTORE_SUCCESS_MESSAGE = 'El recurso ha sido restaurado exitosamente.';

    public const RESTOREALL_SUCCESS_MESSAGE = 'Los recursos han sido restaurados exitosamente.';
    public const RESTOREALL_INCOMPLETE_SUCCESS_MESSAGE = 'Los recursos han sido restaurados exitosamente, no encontrando algunos de ellos.';
    public const RESTOREALL_NOTFOUND_SUCCESS_MESSAGE = 'No se encontraron recursos para restaurar.';

    public const LIST_TITLE = "Litado de recursos";
    public const LIST_MESSAGE = "Listado de recursos obtenidos con éxito.";
    public const ITEM_TITLE = "Recurso";
    public const ITEM_MESSAGE = "Recurso obtenido con éxito.";
    public const ITEM_NOT_FOUND = 'El recurso solicitado no existe';
    public const ITEMS_NOT_FOUND = 'No se encontraron recursos';

    // Constantes de Autorización
    public const LOGIN_TITLE = 'Inicio de sesión con éxito';
    public const LOGIN_MESSAGE = 'El usuario ha iniciado sesión con éxito.';
    public const UNAUTHORIZED_TITLE = 'Acceso no autorizado';
    public const LOGIN_NOT_CREDENTIALES = 'Credenciales incorrectas';
    public const USER_NOT_FOUND = 'El usuario no existe';
    public const LOGOUT_SUCCESS_TITLE = 'Cierre de sesión exitoso';
    public const LOGOUT_SUCCESS_MESSAGE = 'El usuario ha cerrado sesión con éxito.';
    public const TOKEN_UNAUTHORIZED = 'No tiene autorización para acceder. Inicie sesión.';
    public const TOKEN_INVALID = 'Su sesión no es válida. Por favor, inicie sesión nuevamente.';
    public const TOKEN_EXPIRED = 'Su sesión ha caducado. Por favor, vuelva a iniciar sesión.';

    // Constantes de errores de relaciones
    public const TYPEWORKERS_DELETED_ERROR = 'No se puede eliminar porque tiene un trabajador asociado.';
    public const TYPEWORKERS_DELETED_ALL_ERROR = 'No se pueden eliminar porque tienen trabajadores asociados.';
    public const UNITSHIFTS_DELETED_ERROR = 'No se puede eliminar porque tiene una asignación o asistencia asociada.';
    public const UNITSHIFTS_DELETED_ALL_ERROR = 'No se pueden eliminar porque tienen una asignación o asistencia asociada.';
    public const STATEWORKERS_DELETED_ERROR = 'No se puede eliminar porque tiene una asistencia asociada.';
    public const ROLES_DELETED_ERROR = 'No se puede eliminar porque tiene usuario asociado.';
    public const UNITS_DELETED_ERROR = 'No se puede eliminar porque tiene una unidad asociada';
    public const UNITS_DELETED_ALL_ERROR = 'No se pueden eliminar porque tienen unidades asociadas';
    public const DEFAULT_DELETED_ERROR = 'No se puede eliminar porque está asociado a un elemento de otra tabla.';

    // Constantes de errores HTTP
    public const NOTFOUND_DELETED_ERROR = 'El registro solicitado no existe.';
    public const INTERNAL_DELETED_ERROR = 'Error interno del servidor.';
    public const UNAUTHORIZED_DELETED_ERROR = 'Acceso no autorizado.';
    public const FORBIDDEN_DELETED_ERROR = 'Acceso prohibido.';
    public const UNPROCESSABLE_DELETED_ERROR = 'Error de validación.';
    public const GENERAL_DELETED_ERROR = 'Error inesperado.';
}
