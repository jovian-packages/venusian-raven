#version 450
#extension GL_GOOGLE_include_directive : require
#include "raven_effect.glsl"

// An image's picture drawn as a border's background, inside the border's own corner radii.
// params = (u0, v0, u1, v1): the texture coordinates at the rectangle's top-left and
// bottom-right corners. They run past 0..1 where the picture does not reach (fit, centre),
// and nothing is drawn there.
//
// Built with: glslangValidator -V -I<ext-raven>/lib/raven/include/raven/shaders picture.frag -o picture.spv
vec4 raven_effect(RavenEffect fx) {
    vec2 at = mix(fx.params.xy, fx.params.zw, fx.uv);
    if (any(lessThan(at, vec2(0.0))) || any(greaterThan(at, vec2(1.0)))) {
        return vec4(0.0);
    }

    return raven_texture(at) * fx.color;
}
