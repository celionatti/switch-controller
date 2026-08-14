<?php

declare(strict_types=1);

namespace Switch\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

interface ResourceController
{
    /**
     * Display a listing of the resource.
     */
    public function index(ServerRequestInterface $request): ResponseInterface|string;

    /**
     * Show the form for creating a new resource.
     */
    public function create(ServerRequestInterface $request): ResponseInterface|string;

    /**
     * Store a newly created resource in storage.
     */
    public function store(ServerRequestInterface $request): ResponseInterface|string;

    /**
     * Display the specified resource.
     */
    public function show(ServerRequestInterface $request, string|int $id): ResponseInterface|string;

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ServerRequestInterface $request, string|int $id): ResponseInterface|string;

    /**
     * Update the specified resource in storage.
     */
    public function update(ServerRequestInterface $request, string|int $id): ResponseInterface|string;

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ServerRequestInterface $request, string|int $id): ResponseInterface|string;
}
