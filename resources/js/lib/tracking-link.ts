export function buildTrackingLink(
    uuid: string | null,
    trafficAccountId: string,
    trafficAccountSlugs: Record<string, string>,
    trafficSourceTemplates: Record<string, string>,
    baseUrl: string,
    redirectPath: string
): string {
    if (!trafficAccountId) return "";
    const slug = trafficAccountSlugs[trafficAccountId];
    if (!slug) return "";
    const template = trafficSourceTemplates[slug];
    if (!template) return "";

    const base = baseUrl.replace(/\/$/, "");
    const path = "/" + redirectPath.replace(/^\//, "");
    const uuidPart = uuid ?? "{uuid}"; // literal placeholder pre-save

    return `${base}${path}?campaign_uuid=${uuidPart}&${template}`;
}