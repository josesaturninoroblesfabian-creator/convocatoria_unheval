export const academicCredentialLimit = 30;

export function screeningRecordId() {
    // getRandomValues also works when testing over HTTP on a local network.
    const bytes = crypto.getRandomValues(new Uint8Array(16));
    bytes[6] = (bytes[6] & 0x0f) | 0x40;
    bytes[8] = (bytes[8] & 0x3f) | 0x80;
    const hex = Array.from(bytes, (byte) =>
        byte.toString(16).padStart(2, "0"),
    ).join("");
    return [
        hex.slice(0, 8),
        hex.slice(8, 12),
        hex.slice(12, 16),
        hex.slice(16, 20),
        hex.slice(20),
    ].join("-");
}

export const academicCredentialLabels = {
    bachiller: "Bachiller",
    titulo: "Título profesional",
    maestro: "Maestro / Magíster",
    doctor: "Doctor",
    segunda_especialidad: "Segunda especialidad",
};

export const additionalAcademicPoints = {
    bachiller: 1,
    segunda_especialidad: 6,
};

export const defaultScreeningRules = () => ({
    enabled: true,
    academic_credentials: true,
    allow_residency: false,
    minimum_degree: "maestro",
    minimum_experience_months: 60,
    experience_type: "toda",
    count_from_bachelor: true,
    minimum_training_hours: 0,
    degree_points: {
        titulo: 2,
        maestro: 6,
        doctor: 8,
        ...additionalAcademicPoints,
    },
    experience_points_per_year: 1,
    experience_cap: 10,
    training_hours_per_point: 40,
    training_cap: 5,
    fields: [],
});
