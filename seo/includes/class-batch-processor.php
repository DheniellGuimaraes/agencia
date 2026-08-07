<?php
if (!defined('ABSPATH')) {
    exit;
}

class SES_Batch_Processor {
    public static function status_label($status) {
        $labels = array(
            'protegida' => 'Protegida',
            'elegivel' => 'Elegível',
            'enriquecida' => 'Enriquecida',
            'enriquecida_com_alerta' => 'Enriquecida com alerta',
            'pendente' => 'Pendente',
            'rejeitada_por_similaridade' => 'Rejeitada por similaridade',
            'erro' => 'Erro',
            'restaurada' => 'Restaurada',
            'ignorada' => 'Ignorada',
        );
        return $labels[$status] ?? ucfirst((string) $status);
    }
}
