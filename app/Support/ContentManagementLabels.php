<?php

namespace App\Support;

use App\Models\ContentArticle;
use App\Models\ContentArticleStep;

class ContentManagementLabels
{
    /**
     * @return array<string, string>
     */
    public static function mainStatusOptions(): array
    {
        return [
            ContentArticle::MAIN_STATUS_PENDING => 'Pendiente',
            ContentArticle::MAIN_STATUS_PROCESSING => 'En proceso',
            ContentArticle::MAIN_STATUS_UNPUBLISHED => 'Sin publicar',
            ContentArticle::MAIN_STATUS_PUBLISHED => 'Publicado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function operationalStageOptions(): array
    {
        return [
            ContentArticle::STAGE_PENDING => 'Pendiente',
            ContentArticle::STAGE_STRATEGIC_REFINEMENT => 'Definición estratégica',
            ContentArticle::STAGE_DRAFTING => 'Redacción del artículo',
            ContentArticle::STAGE_VIDEO_INSTAGRAM => 'Video e Instagram',
            ContentArticle::STAGE_FINAL_FILE => 'Archivo final',
            ContentArticle::STAGE_COMPLETED => 'Completado',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function stepStatusOptions(): array
    {
        return [
            ContentArticleStep::STATUS_PENDING => 'Pendiente',
            ContentArticleStep::STATUS_IN_PROGRESS => 'En proceso',
            ContentArticleStep::STATUS_READY => 'Listo',
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function stepTypeOptions(): array
    {
        return [
            ContentArticleStep::TYPE_OBJECTIVE => 'Objetivo y público',
            ContentArticleStep::TYPE_DRAFTING => 'Redacción del artículo',
            ContentArticleStep::TYPE_VIDEO_INSTAGRAM => 'Video e Instagram',
        ];
    }

    public static function mainStatus(?string $status): string
    {
        return self::label(self::mainStatusOptions(), $status);
    }

    public static function operationalStage(?string $stage): string
    {
        return self::label(self::operationalStageOptions(), $stage);
    }

    public static function stepStatus(?string $status): string
    {
        return self::label(self::stepStatusOptions(), $status);
    }

    public static function stepType(?string $type): string
    {
        return self::label(self::stepTypeOptions(), $type);
    }

    /**
     * @param  array<string, string>  $options
     */
    private static function label(array $options, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        return $options[$value] ?? $value;
    }
}
