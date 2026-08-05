<?php

namespace App\Facturacion\Infrastructure\Arca\Certificado;

use App\Facturacion\Infrastructure\Arca\Cifrado\CertificadoEncryptor;
use App\Facturacion\Infrastructure\Arca\Exceptions\CertificadoInvalidoException;
use App\Models\CertificadoFiscal;

/**
 * Almacena y recupera certificados pfx encriptados (arquitectura §17, invariante 9).
 * El certificado privado nunca sale del servidor: solo se entrega como
 * CertificadoMaterial en memoria para firmar en WSAA.
 */
final class CertificadoService
{
    public function __construct(
        private CertificadoEncryptor $cifrado,
        private PfxParser $parser,
    ) {}

    public function almacenar(int $comercioId, string $entorno, string $pfx, string $password): CertificadoFiscal
    {
        $datos = $this->parser->parsear($pfx, $password);

        if (! $datos->vigente()) {
            throw new CertificadoInvalidoException('El certificado está vencido.');
        }

        return CertificadoFiscal::create([
            'comercio_id' => $comercioId,
            'entorno' => $entorno,
            'archivo_pfx' => $this->cifrado->encriptar($pfx),
            'password_pfx' => $this->cifrado->encriptar($password),
            'distinguished_name' => $datos->distinguishedName(),
            'numero_serie' => $datos->numeroSerie(),
            'vigencia_desde' => $datos->vigenciaDesde(),
            'vigencia_hasta' => $datos->vigenciaHasta(),
        ]);
    }

    /**
     * Material en memoria para firmar, dado el certificado almacenado.
     */
    public function materialDelModelo(CertificadoFiscal $certificado): CertificadoMaterial
    {
        return new CertificadoMaterial(
            $this->cifrado->desencriptar($certificado->archivo_pfx),
            $this->cifrado->desencriptar($certificado->password_pfx),
        );
    }

    public function materialPara(int $comercioId, string $entorno): CertificadoMaterial
    {
        $certificado = CertificadoFiscal::where('comercio_id', $comercioId)
            ->where('entorno', $entorno)
            ->latest('id')
            ->first();

        if ($certificado === null) {
            throw new CertificadoInvalidoException('No hay certificado cargado para el entorno solicitado.');
        }

        return $this->materialDelModelo($certificado);
    }

    public function vigenciaDelMaterial(CertificadoMaterial $material): PfxDatos
    {
        return $this->parser->parsear($material->pfx(), $material->password());
    }
}
