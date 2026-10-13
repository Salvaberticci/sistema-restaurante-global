<?php

namespace App\Livewire\Kitchen;

use App\Domain\Catalogos\Models\EstacionCocina;
use App\Domain\Configuracion\Contracts\Parametros;
use App\Domain\Iam\Permisos;
use App\Domain\Ordenes\Enums\EstadoOrden;
use App\Domain\Ordenes\Models\Orden;
use App\Domain\Ordenes\Models\OrdenItem;
use App\Domain\Shared\Support\FechaFiscal;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Pantalla de cocina.
 *
 * Livewire y Blade a propósito, no Filament (invariante I7). Una pantalla de
 * cocina no es backoffice: está colgada de un televisor detrás del mostrador, la
 * mira gente con las manos ocupadas y cada segundo de menú, botón o confirmación
 * de sesión se paga en tiempo perdido frente al cliente.
 *
 * La pantalla se refresca sola con `wire:poll`. No hay WebSocket porque en este
 * hosting compartido no hay, y un polling de dos segundos es indistinguible de
 * tiempo real a simple vista — con la diferencia de que no hay que mantener un
 * proceso residente en un servidor donde viven otros diecisiete proyectos.
 *
 * El semáforo sale del parámetro `cocina.semaforo_minutos`, no de una constante.
 * Si una cocina tarda más de lo que el jefe considera aceptable, se cambia el
 * parámetro y no se toca el código.
 */
class Cocina extends Component
{
    use WithPagination;

    /** Actualización en vivo. Dos segundos, según la especificación. */
    public int $periodo = 2000;

    /** `pendientes` muestra lo que falta; `listos`, lo que espera que alguien lo pase. */
    public string $vista = 'pendientes';

    /**
     * El permiso se comprueba acá y no como middleware de la ruta.
     *
     * Dos motivos. El primero es el orden: `can:` puede ejecutarse antes que
     * `auth` y devolver 403 a un visitante que solo necesitaba iniciar sesión.
     * El segundo es el importante: los botones y el `wire:poll` viajan a
     * `/livewire/update`, que no vuelve a pasar por los middleware de la ruta.
     * Un permiso comprobado solo en la ruta deja la pantalla abierta de par en
     * par después de entrar.
     */
    public function mount(): void
    {
        abort_unless(auth()->user()?->can(Permisos::COCINA_VER), 403);
    }

    public function render(): View
    {
        return view('livewire.cocina', [
            'estaciones' => $this->estaciones(),
            'tickets' => $this->tickets(),
            'semaforo' => $this->semaforo(),
            'resumen' => $this->resumen(),
            'umbrales' => $this->umbrales(),
        ])->title('Cocina');
    }

    /**
     * Las comandas que hay que trabajar ahora.
     *
     * Se limita a las de ayer y hoy a propósito. Una comanda de hace tres días
     * que quedó abierta no es trabajo pendiente: es una mesa que se sentó, se
     * fue y nunca cobró, y verla en la pantalla de cocina esconde las que sí
     * importan.
     *
     * @return Collection<int, Orden>
     */
    public function tickets(): Collection
    {
        $estados = $this->vista === 'listos'
            ? ['listo']
            : ['pendiente', 'en_preparacion'];

        return Orden::query()
            ->with([
                'mesa:id,nombre',
                'mesero:id,name',
                'items' => fn ($query) => $query->whereIn('estado', $estados)->orderBy('id'),
            ])
            ->whereIn('estado', [
                EstadoOrden::EnviadaCocina->value,
                EstadoOrden::Servida->value,
            ])
            ->whereDate('abierta_at', '>=', FechaFiscal::hoy()->sumarDias(-1)->iso())
            ->orderByDesc('abierta_at')
            ->limit(60)
            ->get();
    }

    /**
     * Minutos de espera por estado, para el semáforo.
     *
     * @return array{ok: int, avisos: int, tarde: int}
     */
    public function semaforo(): array
    {
        $umbrales = $this->umbrales();
        $ahora = now();

        $minutos = OrdenItem::query()
            ->whereIn('estado', ['pendiente', 'en_preparacion'])
            ->where('created_at', '>=', $ahora->copy()->startOfDay())
            ->get(['created_at'])
            ->map(fn (OrdenItem $item) => (int) $item->created_at->diffInMinutes($ahora));

        return [
            'ok' => $minutos->filter(fn (int $m) => $m <= $umbrales['verde'])->count(),
            'avisos' => $minutos->filter(fn (int $m) => $m > $umbrales['verde'] && $m <= $umbrales['ambar'])->count(),
            'tarde' => $minutos->filter(fn (int $m) => $m > $umbrales['ambar'])->count(),
        ];
    }

    /**
     * Los umbrales del semáforo, en minutos, leídos del parámetro.
     *
     * `cocina.semaforo_minutos` no es un número sino los tres niveles con su
     * techo: verde, ámbar y rojo. Leerlo como un entero devolvía un arreglo, el
     *Umbral quedaba en cero y **todas** las comandas salían en rojo: el semáforo
     * que sirve para avisar de un atraso empezaba diciendo que todo estaba
     * atrasado, que es lo peor que puede hacer un semáforo.
     *
     * Los tops de los parámetros van como texto porque los porcentajes también
     * son texto en la base (invariante I2: el dinero y los porcentajes no son
     * float). Por eso el cast es explícito.
     *
     * @return array{verde: int, ambar: int, rojo: int}
     */
    public function umbrales(): array
    {
        $porDefecto = ['verde' => 15, 'ambar' => 20, 'rojo' => 30];

        $parametro = app(Parametros::class)->obtener('cocina.semaforo_minutos');

        if (! is_array($parametro)) {
            return $porDefecto;
        }

        foreach ($parametro as $nivel) {
            if (is_array($nivel) && isset($nivel['nivel'], $nivel['minutos'])) {
                $porDefecto[$nivel['nivel']] = (int) $nivel['minutos'];
            }
        }

        return $porDefecto;
    }

    /** @return array<int, string> */
    public function estaciones(): array
    {
        return EstacionCocina::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->pluck('nombre')
            ->all();
    }

    /**
     * Conteo por estado, solo del día.
     *
     * Los "servidos" son los del turno que está corriendo, no los de toda la
     * historia: un KDS que dice «15.836 servidos» no informa a nadie, solo
     * esconde que hoy hubo uno solo. El histórico se mira en Estadísticas.
     *
     * @return array{pendientes: int, preparacion: int, listos: int, servidos: int}
     */
    public function resumen(): array
    {
        $hoy = now()->startOfDay();

        return [
            'pendientes' => OrdenItem::query()->where('estado', 'pendiente')->where('created_at', '>=', $hoy)->count(),
            'preparacion' => OrdenItem::query()->where('estado', 'en_preparacion')->where('created_at', '>=', $hoy)->count(),
            'listos' => OrdenItem::query()->where('estado', 'listo')->where('created_at', '>=', $hoy)->count(),
            'servidos' => OrdenItem::query()->where('estado', 'servido')->where('created_at', '>=', $hoy)->count(),
        ];
    }

    /**
     * Pasa un plato al estado siguiente.
     *
     * La secuencia es pendiente → en preparación → listo → servido, y solo se
     * permite un paso a la vez. Que se compruebe contra el estado real del ítem y
     * no contra lo que el navegador cree es lo importante: con varias tablets
     * sobre la misma comanda, dos cocineros pulsando a la vez se saltarían un
     * estado y dejarían un plato sin pasar por "listo" — que es el estado que
     * le avisa al mesero que la mesa ya está servida.
     */
    public function moverItem(int $ordenId, int $itemId, string $nuevo): void
    {
        $item = OrdenItem::query()
            ->where('id', $itemId)
            ->where('orden_id', $ordenId)
            ->first();

        if ($item === null) {
            return;
        }

        $siguiente = match ($item->estado) {
            'pendiente' => 'en_preparacion',
            'en_preparacion' => 'listo',
            'listo' => 'servido',
            default => null,
        };

        if ($siguiente === null || $nuevo !== $siguiente) {
            // Otro ya lo movió, o se pidió un salto. No es un error de este
            // usuario: el refresco de dos segundos lo resuelve solo.
            return;
        }

        $item->estado = $siguiente;
        $item->save();

        $this->actualizarEstadoDeLaOrden($item);
    }

    /** Marca la comanda entera como servida. */
    public function servirOrden(int $ordenId): void
    {
        OrdenItem::query()
            ->where('orden_id', $ordenId)
            ->whereIn('estado', ['pendiente', 'en_preparacion', 'listo'])
            ->update(['estado' => 'servido']);

        $orden = Orden::find($ordenId);

        if ($orden !== null && $orden->estado === EstadoOrden::EnviadaCocina) {
            $orden->estado = EstadoOrden::Servida;
            $orden->cerrada_at = now();
            $orden->save();
        }
    }

    /**
     * Al completar todos los ítems, la orden pasa sola a "servida".
     *
     * Sin esto, la cocina terminaría los platos y la comanda seguiría en
     * pantalla como "en cocina", y el mesero no se enteraría hasta ir a
     * preguntar. Es justo el motivo por el que el KDS existe y no un tablón.
     */
    private function actualizarEstadoDeLaOrden(OrdenItem $item): void
    {
        $orden = Orden::find($item->orden_id);

        if ($orden === null || $orden->estado !== EstadoOrden::EnviadaCocina) {
            return;
        }

        $pendientes = OrdenItem::query()
            ->where('orden_id', $orden->id)
            ->whereIn('estado', ['pendiente', 'en_preparacion'])
            ->count();

        if ($pendientes > 0) {
            return;
        }

        $orden->estado = EstadoOrden::Servida;
        $orden->cerrada_at = now();
        $orden->save();
    }

    public function cambiarVista(string $vista): void
    {
        $this->vista = $vista === 'listos' ? 'listos' : 'pendientes';
    }
}
