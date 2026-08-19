<?php

namespace App\Http\Controllers;

use App\Services\SshKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class SshKeyController extends Controller
{
    public function __construct(
        private SshKeyService $sshService
    ) {}

    /**
     * Get the server's local public SSH key.
     */
    public function getPublicKey(): JsonResponse
    {
        try {
            $key = $this->sshService->getPublicKey();
            $path = $this->sshService->getPublicKeyPath();

            return response()->json([
                'public_key' => $key,
                'path'       => $path,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'error'   => 'Impossible de charger la clé publique',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Test SSH connection to a single node.
     */
    public function testConnection(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'node' => 'required|string',
        ]);

        $result = $this->sshService->testConnection($validated['node']);

        return response()->json($result);
    }

    /**
     * Test SSH connections to a list of nodes.
     */
    public function testAll(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'nodes'   => 'required|array',
            'nodes.*' => 'string',
        ]);

        $results = $this->sshService->testBatch($validated['nodes']);

        return response()->json([
            'nodes' => $results,
        ]);
    }

    /**
     * Authorize SSH public key on a remote node using root password.
     */
    public function authorize(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'node'     => 'required|string',
            'password' => 'required|string',
        ]);

        try {
            $result = $this->sshService->authorizeKey($validated['node'], $validated['password']);

            $status = $result['success'] ? 200 : 422;
            return response()->json($result, $status);
        } catch (Throwable $e) {
            return response()->json([
                'node'    => $validated['node'],
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
