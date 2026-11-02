import { usePage } from "@inertiajs/vue3";
export function url(path = "/") {
    return `${usePage().props.baseUrl || ""}${path}`;
}
export const date = (value, time = false) =>
    value
        ? new Intl.DateTimeFormat("es-PE", {
              day: "2-digit",
              month: "short",
              year: "numeric",
              timeZone: "America/Lima",
              ...(time
                  ? { hour: "2-digit", minute: "2-digit", second: "2-digit" }
                  : {}),
          }).format(new Date(value))
        : "Por definir";
export const labels = {
    BORRADOR: "Borrador",
    PUBLICADA: "Publicada",
    EN_EVALUACION: "En evaluación",
    FINALIZADA: "Finalizada",
    ARCHIVADA: "Archivada",
    RECIBIDA: "Recibida",
    APTO: "Apto",
    NO_APTO: "No apto",
    EVALUADA: "Evaluada",
    NOMBRAMIENTO: "Nombramiento",
    CONTRATO: "Contrato",
    AUXILIAR: "Auxiliar",
    ASOCIADO: "Asociado",
    PRINCIPAL: "Principal",
    "TIEMPO COMPLETO": "Tiempo completo",
    "DEDICACIÓN EXCLUSIVA": "Dedicación exclusiva",
    "TIEMPO PARCIAL": "Tiempo parcial",
};
export const label = (value) => labels[value] || value;
export const shortRef = (value) => value?.slice(0, 8).toUpperCase();
