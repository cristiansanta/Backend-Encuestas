#!/bin/bash

echo "🚀 Iniciando implementación del sistema de permisos de visibilidad..."

# Ejecutar la migración para agregar los campos de permisos
echo "📊 Ejecutando migración de permisos..."
php artisan migrate --path=database/migrations/2025_01_02_000001_add_visibility_permissions_to_users_table.php

if [ $? -eq 0 ]; then
    echo "✅ Migración ejecutada exitosamente"
else
    echo "❌ Error al ejecutar la migración"
    exit 1
fi

echo "🔧 Actualizando cache de configuración..."
php artisan config:cache
php artisan route:cache

echo "✅ ¡Sistema de permisos de visibilidad implementado exitosamente!"
echo ""
echo "📋 Resumen de cambios implementados:"
echo "   • Nuevos campos en tabla users:"
echo "     - allow_view_questions_categories (boolean)"
echo "     - allow_view_surveys_sections (boolean)"
echo "   • Modales de usuario actualizados con switches de permisos"
echo "   • Lógica de filtrado implementada en controladores de:"
echo "     - Preguntas (QuestionController)"
echo "     - Categorías (CategoryController)" 
echo "     - Secciones (SectionController)"
echo ""
echo "🎯 Funcionalidades:"
echo "   • Los usuarios pueden ahora controlar la visibilidad de su contenido"
echo "   • Operarios pueden ver contenido de superadmins si está permitido"
echo "   • Contenido privado permanece oculto para otros usuarios"
echo ""
echo "⚠️  Recordatorio: Reiniciar el servidor si es necesario para que los cambios tengan efecto."