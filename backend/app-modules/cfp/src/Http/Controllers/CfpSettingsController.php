<?php

declare(strict_types=1);

namespace PHPeste\Cfp\Http\Controllers;

use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use PHPeste\Cfp\Actions\ResolveCfpSettings;
use PHPeste\Cfp\Actions\UpdateCfpSettings;
use PHPeste\Cfp\Data\ProposalForm;
use PHPeste\Cfp\Http\Requests\UpdateCfpSettingsRequest;
use PHPeste\Cfp\Http\Resources\CfpSettingsResource;
use PHPeste\Community\Enums\EditionPermission;

class CfpSettingsController
{
    public function show(ResolveCfpSettings $resolve): CfpSettingsResource
    {
        Gate::authorize('cfp.view-settings');

        return new CfpSettingsResource($resolve->handle())->additional(['meta' => ['form' => ProposalForm::options()]]);
    }

    public function update(UpdateCfpSettingsRequest $request, ResolveCfpSettings $resolve, UpdateCfpSettings $update): CfpSettingsResource
    {
        Gate::authorize(EditionPermission::ManageEdition->value);

        $settings = $resolve->handle();
        $errors = $request->windowErrors($settings);

        throw_if($errors !== [], ValidationException::withMessages($errors));

        return new CfpSettingsResource($update->handle($settings, $request->settings()))->additional(['meta' => ['form' => ProposalForm::options()]]);
    }
}
